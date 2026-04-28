# Locustfile.py para poner en unos contenedores que probarán mi
# granja web en contenedores Docker.
# Este archivo se encarga de definir las tareas que van a realizar los usuarios del test de carga.
from locust import HttpUser, TaskSet, task, between 
 
class P5_jorgelpz(TaskSet): 
    @task(5)
    def load_index(self): 
        self.client.get("/index.php", verify=False) 

    @task(1)
    def load_pagina_aux1(self):
        self.client.get("/paginaAux1.php", verify=False)

    @task(2)
    def load_pagina_aux2(self):
        self.client.get("/paginaAux2.php", verify=False)

    @task(3)
    def submit_form(self):
        form_data = {
            "dato1": "hola",
            "dato2": "mundo"
        }
        self.client.post("/formulario.php", data=form_data, verify=False)
 
class P5_usuarios(HttpUser): 
    tasks = [P5_jorgelpz] 
    wait_time = between(1, 5)

