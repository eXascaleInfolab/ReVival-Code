##############
# parameters #
##############
REVIVAL_MONETDB_RELEASE="Nov2019"
REVIVAL_MONETDB_VERSION="11.35.3"
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
sudo apt install -y python-pip
sudo -H pip2 install numpy
sudo -H pip2 install scipy
sudo -H pip2 install statsmodels

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
# extract
sudo ldconfig
unzip ReVival.zip
mv ReVival-Code-master ReVival

# create db
sudo monetdbd create /var/monetdb5/revival_farm
sudo monetdbd start /var/monetdb5/revival_farm
sudo monetdb create revival
sudo monetdb release revival
sudo monetdb set embedpy=yes revival
sudo monetdb set embedc=yes revival

# upload dump
mv ReVival/.service/revivaldump.zip revivaldump.zip
unzip revivaldump.zip
rm revivaldump.zip
/bin/echo -e "user=monetdb\npassword=monetdb" > .monetdb
mclient -d revival revivaldump.sql
rm revivaldump.sql
rm .monetdb

# add python code to /var/monetdb5
sudo mv ReVival/.service/revival_py /var/monetdb5/revival_py

# add to autostart
/bin/echo -e "Description=Starts_ReVival_database_on_MonetDB\n\nWants=network.target\nAfter=syslog.target network-online.target\n\n[Service]\nType=simple\nExecStart=/usr/local/bin/monetdbd start /var/monetdb5/revival_farm\nRestart=on-failure\nRestartSec=10\nKillMode=process\n\n[Install]\nWantedBy=multi-user.target" | sudo tee /etc/systemd/system/monetdb-revival.service
sudo systemctl enable monetdb-revival
sudo systemctl start monetdb-revival

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
