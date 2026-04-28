FROM ubuntu:22.04

ENV DEBIAN_FRONTEND=noninteractive

RUN apt-get update && apt-get install -y \
        apache2 \
        libapache2-mod-php \
        openssl \
        iptables \
        iproute2 \
        iputils-ping \
    && rm -rf /var/lib/apt/lists/*

RUN a2enmod ssl

# Generate a self-signed cert for direct backend HTTPS access.
# The load balancer terminates TLS externally; this cert is only used
# when hitting a backend directly (e.g. smoke-testing :443 on web1..web8).
RUN mkdir -p /etc/apache2/ssl && \
    openssl req -x509 -newkey rsa:2048 \
        -keyout /etc/apache2/ssl/server.key \
        -out    /etc/apache2/ssl/server.crt \
        -days 365 -nodes \
        -subj "/C=ES/ST=Granada/O=webfarm-project/CN=localhost" \
    && chmod 600 /etc/apache2/ssl/server.key

COPY ./apache/apache-ssl.conf /etc/apache2/sites-available/apache-ssl.conf
RUN a2ensite apache-ssl

COPY ./apache/firewall/iptables-web.sh /iptables-web.sh
COPY ./apache/firewall/entrypoint.sh   /entrypoint.sh
RUN chmod +x /iptables-web.sh /entrypoint.sh

EXPOSE 80 443

ENTRYPOINT ["/entrypoint.sh"]
CMD ["apachectl", "-D", "FOREGROUND"]
