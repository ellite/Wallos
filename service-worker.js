// Legacy entry point. Browsers that registered the worker before it moved to
// service-worker.php keep checking this URL for updates; this stub lets them
// migrate to the version-aware worker instead of being stuck on a 404.
importScripts('service-worker.php');
