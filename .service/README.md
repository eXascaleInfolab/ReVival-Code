# Requirements

- Ubuntu 16/18 or its derivatives
- sudo access

## Deployment of monetdb + revival site + apache + php7.1

```bash
wget https://raw.githubusercontent.com/eXascaleInfolab/ReVival-Code/master/.service/install_full.sh
sh install_full.sh
```
<!---

## Deployment of monetdb only, revival site will be extracted and ready to be put in a necessary webserver folder

```bash
wget https://raw.githubusercontent.com/eXascaleInfolab/ReVival-Code/master/.service/install_dbonly.sh
sh install_dbonly.sh
```

the site will be located in `~/ReVival/ReVival/`

## Deployment of site only, assuming a proper instance of monetdb with its data is already running

```bash
wget https://raw.githubusercontent.com/eXascaleInfolab/ReVival-Code/master/.service/install_siteonly.sh
sh install_siteonly.sh 
``` 
-->

## Deployment of monetdb + revival site + apache + php7.1 as a docker container

```bash
mkdir docker-revival
cd docker-revival
wget https://raw.githubusercontent.com/eXascaleInfolab/ReVival-Code/master/.service/docker-revival/install_full.sh
wget https://raw.githubusercontent.com/eXascaleInfolab/ReVival-Code/master/.service/docker-revival/Dockerfile
wget https://raw.githubusercontent.com/eXascaleInfolab/ReVival-Code/master/.service/docker-revival/dbrun.sh
```

This is the base for building a container. When running it port 8080 can be replaced with any other, same with image and container names. Append sudo to all further commands if docker requires sudo.

```bash
docker image build -t revival:1.0 .
docker run -dit --name rev -p 8080:80 --hostname=revival.local revival:1.0
```

To update the site to the newest version from the repository repeat the same process of re-downloading files, rebuilding the container while the old one is still running and then simply remove the old one and redeploy the new one.

```bash
rm -rf docker-revival
mkdir docker-revival
cd docker-revival
wget https://raw.githubusercontent.com/eXascaleInfolab/ReVival-Code/master/.service/docker-revival/install_full.sh
wget https://raw.githubusercontent.com/eXascaleInfolab/ReVival-Code/master/.service/docker-revival/Dockerfile
wget https://raw.githubusercontent.com/eXascaleInfolab/ReVival-Code/master/.service/docker-revival/dbrun.sh
docker image build -t revival:1.0 .
# this will stop and remove the old container
docker container rm --force rev
# the same command to deploy the new version
docker run -dit --name rev -p 8080:80 --hostname=revival.local revival:1.0
```

Despite everything being done by the scripts, the files have to be downloaded again because they might be updated themselves and also to avoid docker using the cached version of the old build.
