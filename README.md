# Container Web Farm with Load Balancing

A containerized web farm built with Docker, featuring an Nginx load balancer, eight Apache backend servers, a WordPress CMS stack, and two different load-testing setups (Apache Benchmark and Locust).

This project was developed as part of the SWAP (Servidores Web de Altas Prestaciones) course.

> **Full CLI deployment guide (Linux, no Docker Desktop):** [DEPLOYMENT.md](DEPLOYMENT.md)

---

## Architecture

### Web Farm

```
                        ┌─────────────────────────────────────────────┐
                        │               red_web (192.168.10.x)        │
                        │                                             │
           HTTP/HTTPS   │  ┌────────────────────────────────────┐    │
Client ───────────────► │  │  Nginx Load Balancer               │    │
        :80 / :443      │  │  192.168.10.50                     │    │
                        │  │  Round-robin · SSL termination      │    │
                        │  └──┬──┬──┬──┬──┬──┬──┬──────────────┘    │
                        │     │  │  │  │  │  │  │  │                 │
                        │  ┌──▼──▼──▼──▼──▼──▼──▼──▼──────────────┐ │
                        │  │  Apache Web Servers (web1 – web8)     │ │
                        │  │  192.168.10.2 – 192.168.10.9          │ │
                        │  │  PHP app · iptables (LB-only traffic) │ │
                        │  └───────────────────────────────────────┘ │
                        └─────────────────────────────────────────────┘
```

### CMS Stack

```
           HTTP/HTTPS   ┌────────────────────────────────────────────┐
Client ───────────────► │  Nginx Reverse Proxy  (192.168.10.50)      │
        :80 / :443      └────────────────┬───────────────────────────┘
                                         │
                        ┌────────────────▼───────────────────────────┐
                        │  red_servicios (192.168.20.x)              │
                        │                                            │
                        │  WordPress  (192.168.20.11)                │
                        │       │                                    │
                        │  MySQL 5.7  (192.168.20.10)                │
                        └────────────────────────────────────────────┘
```

---

## Project Structure

```
p5/
├── web-farm/                    # 8-server Apache farm + Nginx load balancer
│   ├── docker-compose.yml
│   ├── apache/                  # Apache server image & PHP application
│   │   ├── Dockerfile
│   │   ├── apache-ssl.conf
│   │   ├── firewall/
│   │   │   ├── entrypoint.sh
│   │   │   └── iptables-web.sh
│   │   └── web-content/         # PHP pages served by every backend
│   ├── nginx/                   # Nginx load balancer image
│   │   ├── Dockerfile
│   │   └── nginx.conf
│   └── certs/                   # Self-signed SSL certificates
│
├── cms/                         # WordPress + MySQL + Nginx reverse proxy
│   ├── docker-compose.yml
│   ├── nginx/
│   │   ├── Dockerfile
│   │   └── nginx.conf
│   └── certs/
│
└── load-testing/
    ├── apache-benchmark/        # Apache Benchmark (ab) – raw throughput
    │   ├── docker-compose.yml
    │   └── Dockerfile
    └── locust/                  # Locust – distributed realistic load testing
        ├── docker-compose.yml
        └── locustfile.py
```

---

## Components

### Web Farm (`web-farm/`)

| Container | Image | IP | Ports |
|-----------|-------|----|-------|
| balanceador-nginx | jorgelpz-nginx-image:p4 | 192.168.10.50 | 80, 443 |
| web1 – web8 | jorgelpz-apache-image:p4 | 192.168.10.2–10.9 | 80, 443 |

- The Nginx load balancer distributes requests in **round-robin** across the 8 Apache servers.
- Each Apache server runs **iptables** rules that drop any traffic not coming from the load balancer (192.168.10.50), hardening the internal network.
- An Nginx stats endpoint is available at `/estadisticas_jorgelpz`.

### CMS (`cms/`)

| Container | Image | IP |
|-----------|-------|----|
| balanceador-nginx-wordpress | jorgelpz-nginx-image:p4 | 192.168.10.50 |
| wordpress | wordpress:latest | 192.168.20.11 |
| db | mysql:5.7 | 192.168.20.10 |

- WordPress and MySQL communicate over the internal `red_servicios` network.
- The Nginx proxy is the only entry point exposed to clients.

### Load Testing (`load-testing/`)

#### Apache Benchmark
Fires **10,000 requests** at **100 concurrent connections** against both HTTP and HTTPS endpoints.

#### Locust
Distributed master-worker setup (**1 master + 6 workers**) with a realistic task mix:

| Task | Weight | Endpoint |
|------|--------|----------|
| Load index | 5 | `/` |
| Load auxiliary page 2 | 2 | `/paginaAux2.php` |
| Submit form | 3 | `/formulario.php` (POST) |
| Load auxiliary page 1 | 1 | `/paginaAux1.php` |

Locust's web UI is available at `http://localhost:8089`.

---

## Prerequisites

- Docker Engine 24+
- Docker Compose v2
- The base image `jorgelpz-apache-image:p3` must be available locally (built in the previous practice).

### Docker Networks

Both stacks require two external Docker networks. Create them once:

```bash
docker network create --subnet=192.168.10.0/24 red_web
docker network create --subnet=192.168.20.0/24 red_servicios
```

---

## Usage

### 1. Build custom images (web farm)

Run from the `web-farm/` directory:

```bash
# Apache image (extends p3 base image)
docker build -f apache/Dockerfile -t jorgelpz-apache-image:p4 .

# Nginx load balancer image
docker build -f nginx/Dockerfile -t jorgelpz-nginx-image:p4 .
```

### 2. Start the Web Farm

```bash
cd web-farm/
docker compose up -d
```

Access via `https://localhost:9000` (HTTPS) or `http://localhost:8999` (HTTP).

### 3. Start the CMS

```bash
cd cms/
docker compose up -d
```

On first run, access WordPress directly at `http://localhost:8080` to complete setup, then use the Nginx proxy at `http://localhost:8999`.

### 4. Run Load Tests

**Apache Benchmark** (ensure the web farm is running first):

```bash
cd load-testing/apache-benchmark/
docker compose up
```

**Locust**:

```bash
cd load-testing/locust/
docker compose up
```

Open `http://localhost:8089`, set the target host to `https://192.168.10.50`, configure users and spawn rate, and start the test.

### Stopping everything

```bash
docker compose down          # from each stack's directory
docker compose down -v       # also removes volumes (CMS data)
```

---

## Security Notes

- SSL uses **TLSv1.2 and TLSv1.3** only; weak cipher suites are disabled.
- Certificates included are **self-signed** and intended for development/lab use only. Replace with real certificates for any public deployment.
- Web servers enforce **iptables rules** that restrict incoming connections to the load balancer IP only.
- Database credentials in `cms/docker-compose.yml` are demo values — do not use in production.

---

## Technologies

| Technology | Role |
|------------|------|
| Docker / Docker Compose | Container orchestration |
| Nginx | Load balancer & reverse proxy |
| Apache HTTP Server | Web server (PHP) |
| PHP | Server-side web application |
| WordPress | Content management system |
| MySQL 5.7 | Relational database |
| iptables | Container-level firewall |
| Apache Benchmark (ab) | Raw HTTP load testing |
| Locust | Distributed behaviour-based load testing |
| OpenSSL | Self-signed certificate generation |
