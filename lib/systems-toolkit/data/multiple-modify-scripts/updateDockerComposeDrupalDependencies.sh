reponame="$1" \
yq --inplace '
  [.services | keys | .[] | select(. == "drupal-*")] as $dependencies |
   .services[env(reponame)]["depends_on"] = $dependencies
' docker-compose.yml

#unbscholar.dspace.lib.unb.ca,omeka.mysql.lib.unb.ca,unbhistory.lib.unb.ca,maximilian.lib.unb.ca,mediawiki.mysql.lib.unb.ca,digipres.postgres.lib.unb.ca,unbscholar.solr.lib.unb.ca,unbscholar.lib.unb.ca,unbscholar.postgres.lib.unb.ca,status.lib.unb.ca,lastshift.lib.unb.ca,digipres.solr.lib.unb.ca,exhibits.lib.unb.ca,login.lib.unb.ca,crawler.pubcrawler.lib.unb.ca,digipres.dspace.lib.unb.ca,drupal.redis.lib.unb.ca,go.lib.unb.ca,defaultbackend.k8s.lib.unb.ca,drupal.mysql.lib.unb.ca,digipres.lib.unb.ca,ospos.mysql.lib.unb.ca,drupal.solr.lib.unb.ca

