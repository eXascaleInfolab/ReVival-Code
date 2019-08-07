# Requirements

- Ubuntu 16/18 or its derivatives
- sudo access

## Deployment of monetdb + revival site + apache + php7.1

```bash
wget https://raw.githubusercontent.com/eXascaleInfolab/ReVival-Code/master/.service/install_full.sh
sh install_full.sh
```

## Deployment of monetdb only, revival site will be extracted and ready to be put in a necessary webserver folder

```bash
wget https://raw.githubusercontent.com/eXascaleInfolab/ReVival-Code/master/.service/install_dbonly.sh
sh install_dbonly.sh
```

we site will be located in `~/ReVival/ReVival/`

## Deployment of site only, assuming a proper instance of monetdb with its data is already running

```bash
wget https://raw.githubusercontent.com/eXascaleInfolab/ReVival-Code/master/.service/install_siteonly.sh
sh install_siteonly.sh
```
