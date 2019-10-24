# version 1.0
FROM ubuntu:18.04
WORKDIR /usr/src/app
ARG DEBIAN_FRONTEND=noninteractive
RUN apt-get update
RUN apt-get install -y --no-install-recommends apt-utils
RUN apt-get install -y wget
RUN apt-get install -y unzip
COPY . .
RUN sh install_full.sh

CMD ["sh", "dbrun.sh"]