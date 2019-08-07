##############
# parameters #
##############
REVIVAL_MONETDB_RELEASE="Apr2019"
REVIVAL_MONETDB_VERSION="11.33.3"
REVIVAL_CDTOOL_SNAPSHOT="e311b0b60542b0d2059d99fab23febe7bc466513"

#########
# files #
#########

cd ~
rm -rf ReVival
mkdir ReVival
cd ReVival

wget "https://github.com/eXascaleInfolab/ReVival-Code/archive/master.zip" -O ReVival.zip

##################
# set up website #
##################

# extract website, move to www
unzip ReVival.zip
rm ReVival.zip
mv ReVival-Code-master ReVival
rm -rf ReVival/.service/

# php5
sudo apt install -y software-properties-common
sudo add-apt-repository ppa:ondrej/php
sudo apt update
sudo apt install -y php7.1 php7.1-mysql php-gettext php7.1-mbstring php-xdebug libapache2-mod-php7.1

# apache, should not be necessary
sudo apt install -y apache2

# move website to apache
sudo rm /var/www/html/index.html
sudo mv ReVival/* /var/www/html/
