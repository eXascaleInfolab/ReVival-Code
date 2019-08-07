##############
# parameters #
##############
REVIVAL_MONETDB_RELEASE="Apr2019"
REVIVAL_MONETDB_VERSION="11.33.3"
REVIVAL_CDTOOL_SNAPSHOT="e311b0b60542b0d2059d99fab23febe7bc466513"

#################
# prerequisites #
#################
# general
sudo apt -y update
sudo apt -y upgrade

# for building monetdb
sudo apt install -y pkg-config bison libssl-dev libbz2-dev

# for py+c UDFs
sudo apt install -y build-essential
sudo apt install -y clang
sudo apt install -y python-dev
sudo apt install -y python-numpy

#########
# files #
#########

cd ~
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
sudo make install

cd ..

#################
# build CD_tool #
#################
# extract
unzip CD_tool.zip
cd "CD_tool-$REVIVAL_CDTOOL_SNAPSHOT"

# build
make library-monetdb
sudo cp cmake-build-debug/libIncCDMdb.so /usr/local/lib/libIncCDMdb.so

cd ..

##############################
# configure & start database #
##############################

sudo ldconfig
unzip ReVival.zip
mv ReVival-Code-master ReVival

monetdbd create revival_farm
monetdbd start revival_farm
monetdb create revival
monetdb release revival
monetdb set embedpy=yes revival
monetdb set embedc=yes revival

mv ReVival/.service/revivaldump.zip revivaldump.zip
unzip revivaldump.zip
rm revivaldump.zip
echo "user=monetdb\npassword=monetdb" > .monetdb
mclient -d revival revivaldump.sql
rm revivaldump.sql
rm .monetdb

#todo: add to autostart

##################
# set up website #
##################

rm -rf ReVival/.service/

# ready to be moved where necessary

############
# clean up #
############

rm CD_tool.zip
rm MonetDB.tar.xz
rm ReVival.zip
rm -rf "CD_tool-$REVIVAL_CDTOOL_SNAPSHOT"
rm -rf "MonetDB-$REVIVAL_MONETDB_VERSION"
