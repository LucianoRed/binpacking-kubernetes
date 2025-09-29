# binpacking-kubernetes
Repo to do some experiences on binpacking for Kubernetes

## Steps to generate busybox-worker image
```
cd docker-image-busybox
oc new-project demo-build
oc new-build --name=busybox-worker --strategy=docker --binary=true
oc start-build busybox-worker --from-dir=. --follow
oc policy add-role-to-group system:image-puller system:serviceaccounts -n demo-build
```
