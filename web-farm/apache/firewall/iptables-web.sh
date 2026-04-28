#!/bin/bash

# Script: jorgelpz-iptables-web.sh
# Descripción: Este script configura una serie de reglas de iptables para un servidor web.

echo "denegando implicitamente todo el trafico"
iptables -P INPUT DROP
iptables -P OUTPUT DROP
iptables -P FORWARD DROP

echo "manejando el trafico de red entrante basado en el estado de las conexiones"
iptables -A INPUT -m state --state ESTABLISHED,RELATED -j ACCEPT

echo "manejando el trafico de red saliente basado en el estado de las conexiones"
iptables -A OUTPUT -m state --state NEW,ESTABLISHED,RELATED -j ACCEPT

echo "manejando el trafico de red de la propia maquina consigo misma"
iptables -A INPUT -i lo -j ACCEPT
iptables -A OUTPUT -o lo -j ACCEPT

echo "manejando el trafico para el puerto 80 y 443 desde el balanceador de carga"
iptables -A INPUT -p tcp -s 192.168.10.50 --dport 80 -j ACCEPT
iptables -A INPUT -p tcp -s 192.168.10.50 --dport 443 -j ACCEPT