FROM jorgelpz-apache-image:p3

RUN apt-get update && apt-get install -y iptables

COPY ./certs/certificado_jorgelpz.crt /etc/apache2/ssl/certificado_jorgelpz.crt
COPY ./certs/certificado_jorgelpz.key /etc/apache2/ssl/certificado_jorgelpz.key
COPY ./apache/apache-ssl.conf /etc/apache2/sites-available/jorgelpz-apache-ssl.conf
COPY ./apache/firewall/iptables-web.sh /iptables-web.sh
COPY ./apache/firewall/entrypoint.sh /entrypoint.sh

RUN chmod 600 /etc/apache2/ssl/certificado_jorgelpz.crt
RUN chmod +x /entrypoint.sh /iptables-web.sh

ENTRYPOINT [ "/entrypoint.sh" ]

CMD ["apachectl", "-D", "FOREGROUND"]
