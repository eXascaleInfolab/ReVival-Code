##############
# parameters #
##############
REVIVAL_MONETDB_RELEASE="Apr2019-SP1"
REVIVAL_MONETDB_VERSION="11.33.11"
REVIVAL_CDTOOL_SNAPSHOT="e311b0b60542b0d2059d99fab23febe7bc466513"

#################
# prerequisites #
#################
# general
apt-get -y update
apt-get -y upgrade

# for building monetdb
apt install -y pkg-config bison libssl-dev libbz2-dev

# for py+c UDFs
apt-get install -y build-essential
apt-get install -y clang
apt-get install -y python-dev
apt-get install -y python-numpy

#########
# files #
#########

rm -rf ReVival
mkdir ReVival
cd ReVival

wget "https://github.com/eXascaleInfolab/ReVival-Code/archive/master.zip" -O ReVival.zip
wget "https://github.com/eXascaleInfolab/CD_tool/archive/$REVIVAL_CDTOOL_SNAPSHOT.zip" -O CD_tool.zip
wget "https://www.monetdb.org/downloads/sources/$REVIVAL_MONETDB_RELEASE/MonetDB-$REVIVAL_MONETDB_VERSION.tar.xz" -O MonetDB.tar.xz

##################
# build database #
##################
# extract
tar -xf MonetDB.tar.xz
cd "MonetDB-$REVIVAL_MONETDB_VERSION"

# build
./configure --enable-pyintegration --disable-odbc --prefix=/usr/local
make
make install

cd ..

#################
# build CD_tool #
#################
# extract
unzip CD_tool.zip
cd "CD_tool-$REVIVAL_CDTOOL_SNAPSHOT"

# build
make library-monetdb
cp cmake-build-debug/libIncCDMdb.so /usr/local/lib/libIncCDMdb.so

cd ..

##############################
# configure & start database #
##############################
# extract
ldconfig
unzip ReVival.zip
mv ReVival-Code-master ReVival

# create db
monetdbd create /var/monetdb5/revival_farm
monetdbd start /var/monetdb5/revival_farm
monetdb create revival
monetdb release revival
monetdb set embedpy=yes revival
monetdb set embedc=yes revival

# upload dump
mv ReVival/.service/revivaldump.zip revivaldump.zip
unzip revivaldump.zip
rm revivaldump.zip
/bin/echo -e "user=monetdb\npassword=monetdb" > .monetdb
mclient -d revival revivaldump.sql
rm revivaldump.sql
rm .monetdb

##################
# set up website #
##################

# move to www
rm -rf ReVival/.service/

# php5
apt-get install -y software-properties-common
add-apt-repository -y ppa:ondrej/php
apt-get -y update
apt-get install -y php7.1 php7.1-mysql php-gettext php7.1-mbstring php-xdebug libapache2-mod-php7.1

# apache, should not be necessary
apt install -y apache2

# move website to apache
rm /var/www/html/index.html
mv ReVival/* /var/www/html/

############
# clean up #
############

rm CD_tool.zip
rm MonetDB.tar.xz
rm ReVival.zip
rm -rf ReVival
rm -rf "CD_tool-$REVIVAL_CDTOOL_SNAPSHOT"
rm -rf "MonetDB-$REVIVAL_MONETDB_VERSION"
