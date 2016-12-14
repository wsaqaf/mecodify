FROM tutum/lamp:latest

# the latest version of pvanheus' fork with SQL bug fixed
RUN rm -fr /app && git clone https://github.com/pvanheus/mecodify /app

# These pull a particular version and deploy it, but currently v. 1.21 doesn't
# work with the MySQL 5.5 used by this container (due to `timestamp` vs `datetime` error)

# RUN apt-get update && apt-get install -y wget
# RUN rm -rf /app && wget -O - https://github.com/wsaqaf/mecodify/archive/1.21.tar.gz | tar -zxC /tmp -f - && mv /tmp/mecodify-1.21 /app
ADD configurations.php /app/configurations.php
WORKDIR /app
RUN mkdir site_config

VOLUME ["/app/site_config"]

# To allow persistent configuration and MySQL database
VOLUME ["/etc/mysql", "/var/lib/mysql"]

EXPOSE 80 3306

CMD ["/run.sh"]
