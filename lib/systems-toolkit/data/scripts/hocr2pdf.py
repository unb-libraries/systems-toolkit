"""Builds searchable single-page PDFs from page images and their existing hOCR.

This avoids a second Tesseract pass when hOCR has already been generated: the
source JPG is embedded unchanged (img2pdf passthrough) and an invisible text
layer rendered from the hOCR (ocrmypdf's HocrTransform) is overlaid on it.
The output matches Tesseract's own PDF renderer in page size and text-layer
character positions.

Runs inside the ocrmypdf docker image, which provides img2pdf, pikepdf and
ocrmypdf.

Usage:
  hocr2pdf.py [--dpi 300] [--workers N] IMAGE HOCR OUT [IMAGE HOCR OUT ...]
"""

import argparse
import sys
import tempfile
from concurrent.futures import ProcessPoolExecutor
from pathlib import Path

import img2pdf
import pikepdf
from ocrmypdf.hocrtransform import HocrTransform


def build_pdf(image: str, hocr: str, out: str, dpi: float) -> str:
    """Writes one searchable PDF page for an image and its hOCR."""
    layout = img2pdf.get_fixed_dpi_layout_fun((dpi, dpi))
    with tempfile.TemporaryDirectory() as tmp:
        text_pdf = Path(tmp) / 'text.pdf'
        HocrTransform(hocr_filename=Path(hocr), dpi=dpi).to_pdf(
            out_filename=text_pdf, image_filename=None, invisible_text=True
        )
        image_pdf = Path(tmp) / 'image.pdf'
        image_pdf.write_bytes(img2pdf.convert(image, layout_fun=layout))
        with pikepdf.open(image_pdf) as base, pikepdf.open(text_pdf) as text:
            base.pages[0].add_overlay(text.pages[0])
            base.save(out)
    return out


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--dpi', type=float, default=300.0)
    parser.add_argument('--workers', type=int, default=4)
    parser.add_argument('files', nargs='+')
    args = parser.parse_args()

    if len(args.files) % 3:
        parser.error('Files must be given as IMAGE HOCR OUT triples.')
    jobs = [args.files[i:i + 3] for i in range(0, len(args.files), 3)]

    failures = 0
    with ProcessPoolExecutor(max_workers=max(1, args.workers)) as pool:
        futures = {
            pool.submit(build_pdf, image, hocr, out, args.dpi): image
            for image, hocr, out in jobs
        }
        for future, image in futures.items():
            try:
                print(f'Built {future.result()}')
            except Exception as e:  # Report every failure, then exit non-zero.
                failures += 1
                print(f'FAILED {image}: {e}', file=sys.stderr)
    return 1 if failures else 0


if __name__ == '__main__':
    sys.exit(main())
