# Deployment Guide — Linux CLI (no Docker Desktop)

Step-by-step guide to bring up the full stack from a clean Linux machine using only the terminal.

---

## 1. Install Docker Engine

> Skip this section if Docker is already installed (`docker --version`).

```bash
# Remove old versions if present
sudo apt-get remove docker docker-engine docker.io containerd runc

# Install dependencies
sudo apt-get update
sudo apt-get install -y ca-certificates curl gnupg lsb-release

# Add Docker's official GPG key
sudo mkdir -p /etc/apt/keyrings
curl -fsSL https://download.docker.com/linux/ubuntu/gpg \
  | sudo gpg --dearmor -o /etc/apt/keyrings/docker.gpg

# Add the Docker repository
echo \
  "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.gpg] \
  https://download.docker.com/linux/ubuntu $(lsb_release -cs) stable" \
  | sudo tee /etc/apt/sources.list.d/docker.list > /dev/null

# Install Docker Engine + Compose plugin
sudo apt-get update
sudo apt-get install -y docker-ce docker-ce-cli containerd.io docker-compose-plugin
```

Add your user to the `docker` group so you don't need `sudo` on every command:

```bash
sudo usermod -aG docker $USER
newgrp docker          # apply without logging out
```

Verify the installation:

```bash
docker --version
docker compose version
```

---

## 2. Create Docker Networks

Both stacks share two external networks that must exist before any `docker compose up`.
Run this once — networks persist across reboots.

```bash
docker network create --driver=bridge --subnet=192.168.10.0/24 red_web
docker network create --driver=bridge --subnet=192.168.20.0/24 red_servicios
```

Confirm they exist:

```bash
docker network ls | grep -E "red_web|red_servicios"
```

Expected output:
```
<id>   red_web        bridge    local
<id>   red_servicios  bridge    local
```

---

## 3. Web Farm

### 3.1 Build the images

> **Prerequisite:** the base image `jorgelpz-apache-image:p3` must exist locally.
> It was built in the previous practice (P4). If missing, check that repo first.

Run from the `web-farm/` directory — the build context must be `web-farm/` so the
`COPY` paths inside the Dockerfile resolve correctly.

```bash
cd web-farm/

# Apache image (extends the p3 base)
docker build \
  -f apache/Dockerfile \
  -t jorgelpz-apache-image:p4 \
  .

# Nginx load balancer image
docker build \
  -f nginx/Dockerfile \
  -t jorgelpz-nginx-image:p4 \
  .
```

Confirm both images are available:

```bash
docker images | grep jorgelpz
```

### 3.2 Start the web farm

```bash
cd web-farm/
docker compose up -d
```

Check all 9 containers (8 web servers + 1 load balancer) are running:

```bash
docker compose ps
```

All entries should show `running` / `Up`.

### 3.3 Verify the web farm

**Load balancer — HTTP:**
```bash
curl http://localhost:8999/
```

**Load balancer — HTTPS** (self-signed cert, `-k` skips verification):
```bash
curl -k https://localhost:9000/
```

**Round-robin distribution** — run this a few times and watch the hostname change:
```bash
for i in $(seq 1 8); do
  curl -sk https://localhost:9000/ | grep -o 'hostname.*<'
done
```

Each request should hit a different backend (`web1` through `web8`).

**Individual servers** (direct access, bypassing the load balancer):
```bash
for port in 8081 8082 8083 8084 8085 8086 8087 8088; do
  echo -n "Port $port → "
  curl -s http://localhost:$port/ | grep -o 'hostname[^<]*' | head -1
done
```

**Nginx stats endpoint:**
```bash
curl http://localhost:8999/estadisticas_jorgelpz
```

Returns active connections, requests handled, and reading/writing/waiting states.

**Container logs** (live follow):
```bash
docker compose logs -f balanceador-nginx
docker compose logs -f web1
```

---

## 4. CMS (WordPress + MySQL + Nginx)

### 4.1 Build the Nginx image

> The Nginx image (`jorgelpz-nginx-image:p4`) was already built in step 3.1.
> If you skipped the web farm, build it now:

```bash
cd cms/
docker build \
  -f nginx/Dockerfile \
  -t jorgelpz-nginx-image:p4 \
  .
```

### 4.2 Start the CMS stack

```bash
cd cms/
docker compose up -d
```

```bash
docker compose ps
# Should show: db, wordpress, balanceador-nginx-wordpress — all Up
```

### 4.3 First-time WordPress setup

On first run, access WordPress **directly** (bypassing Nginx) to complete the installer:

```
http://localhost:8080
```

Choose language, set site title, admin username/password, and email. Once done, the
Nginx proxy is ready:

```
http://localhost:8999
```

**Verify the proxy is routing to WordPress:**
```bash
curl -si http://localhost:8999/ | head -5
```

Expect a `200 OK` or `302` redirect to the WordPress front page.

**Database connectivity check:**
```bash
docker exec -it db mysql -u wp_user -pwp_pass -e "SHOW DATABASES;"
```

Expected output includes the `wordpress` database.

---

## 5. Load Testing

> The web farm must be running before executing any load tests.

### 5.1 Apache Benchmark

Builds the `ab` image and fires 10,000 requests at 100 concurrent connections
against both HTTP and HTTPS.

```bash
cd load-testing/apache-benchmark/
docker compose up
```

Results print directly to the terminal when each container finishes. Look for:

- **Requests per second** — throughput
- **Time per request** — average latency
- **Failed requests** — should be 0

### 5.2 Locust (distributed)

```bash
cd load-testing/locust/
docker compose up -d
```

Open the Locust web UI in a browser:

```
http://localhost:8089
```

Configure the test:
- **Number of users:** e.g. 100
- **Spawn rate:** e.g. 10 users/second
- **Host:** `https://192.168.10.50` (the load balancer inside the Docker network)

Click **Start** and monitor the real-time charts for RPS, response times, and failures.

**Check workers are connected:**
```bash
docker compose logs master-jorgelpz | grep "worker"
```

**Stop Locust:**
```bash
docker compose down
```

---

## 6. Monitoring & Useful Commands

### Container status

```bash
docker ps                         # all running containers
docker ps -a                      # including stopped containers
docker stats                      # live CPU / memory / network usage
```

### Network inspection

```bash
# See which containers are attached to red_web and their IPs
docker network inspect red_web | grep -A5 '"Name"'

# Same for the internal services network
docker network inspect red_servicios | grep -A5 '"Name"'
```

### Logs

```bash
docker logs <container_name>          # full log
docker logs -f <container_name>       # follow (live)
docker logs --tail 50 <container_name>
```

### Execute commands inside a container

```bash
docker exec -it web1 bash            # open a shell in web1
docker exec -it balanceador-nginx nginx -t   # test Nginx config
```

### Resource usage per stack

```bash
cd web-farm/ && docker compose stats
```

---

## 7. Tear Down

Stop and remove containers (data volumes are preserved):

```bash
# Web farm
cd web-farm/ && docker compose down

# CMS (add -v to also delete WordPress and MySQL data)
cd cms/ && docker compose down
cd cms/ && docker compose down -v   # ← wipes all CMS data

# Load testing
cd load-testing/apache-benchmark/ && docker compose down
cd load-testing/locust/ && docker compose down
```

Remove networks when fully done:

```bash
docker network rm red_web red_servicios
```

Remove built images:

```bash
docker rmi jorgelpz-apache-image:p4 jorgelpz-nginx-image:p4 jorgelpz-ab-image:p5
```
