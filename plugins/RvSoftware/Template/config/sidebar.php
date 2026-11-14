<nav class="sidebar" aria-label="Configuración de Projecta"><ul>
<?php foreach ([['ConfigController','application','Aplicación'],['ConfigController','project','Proyectos'],['ConfigController','board','Tablero'],['TagController','index','Etiquetas'],['LinkController','show','Relaciones de tareas'],['ConfigController','email','Correo SMTP'],['ConfigController','api','API y conexiones']] as $item): ?>
<li <?= $this->app->checkMenuSelection($item[0],$item[1]) ?>><?= $this->url->link($item[2],$item[0],$item[1]) ?></li>
<?php endforeach ?></ul></nav>
