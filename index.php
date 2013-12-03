<?php

$direccion_envio= ' juanm_ramallo@hotmail.com ' ; //la direccion a la que se enviara el email.
$url= ''; //la URL donde esta publicado el formulario. SIN la barra al final
?>


<?PHP
//proceso del formulario
// si existe "enviar"...
if (isset ($_POST['enviar'])) {

// vamos a hacer uso de la clase phpmailer, 
require("class.phpmailer.php");

$mail = new PHPMailer();//recogemos las variables y configuramos PHPMailer
$mail->From     = $_POST['email'];$mail->FromName = $_POST['nombre'];
$mail->AddAddress($direccion_envio); 
$mail->Subject = "Contacto desde el Formulario de cursos";
$mail->AddReplyTo($_POST['email'],$_POST['nombre']);
$mail->IsHTML(true);                              
$direccion=$_POST['direccion'];
$profesion=$_POST['profesion'];
$c1=$_POST['c1'];
$c1=$_POST['c2'];
$c1=$_POST['c3'];
$group1=$_POST['group1'];
$telefono=$_POST['telefono'];
$mensaje=$_POST['mensaje'];



//comprobamos si se adjunto un archivo, y si su tamano es menor al permitido
if (isset($_FILES['archivo']['tmp_name'])) {
$aleatorio = rand(); 
$nuevonombre=$aleatorio.'-'.$_FILES['archivo']['name'];
}


//comprobamos si todos los campos fueron completados
if ($_POST['email']!='' && $_POST['nombre']!=''  && $error_archivo=='') {

// copiamos el archivo en el servidor
//copy($_FILES['archivo']['tmp_name'],'archivos/'.$nuevonombre);
$tieneAdjunto = false;
if( !empty($_FILES['archivo']['tmp_name']) || 
    !empty($_FILES['archivo']['size']))
{
    if (move_uploaded_file($_FILES['archivo']['tmp_name'],'archivos/'.$nuevonombre)){ 
               $tieneAdjunto = true;
                                 //echo "El archivo ha sido cargado correctamente."; 
           }else{ 
                                 $tieneAdjunto = false;         
               //echo "Ocurrió algún error al subir el fichero. No pudo guardarse. "; 
           }                 
}
//para mostrar loq ue esta guardando la variable _FILES
//echo '<pre>';
//echo ' debugging info:';
//print_r($_FILES);
//print "</pre>";

//armamos el html
$contenido = '<html><body>';
$contenido .= '<h2>Mensaje desde el Formulario de Contacto de cursos</h2>';
$contenido .= '<p>Enviado el '.  date("d M Y").'</p>';
$contenido .= '<hr />';
$contenido .= '<p>Nombre: <strong>'.$_POST['nombre'].'</strong>';
$contenido .= '<p>Direciión Actual: <strong>'.$_POST['direccion'].'</strong>';
$contenido.='<p>Teléfono: <strong>'.$_POST['telefono'].'</strong>';
$contenido.='<p>Correo electrónico: <strong>'.$_POST['email'].'</strong>';
$contenido.='<p>Profesión:<strong>'.$_POST['profesion'].'</strong>';
$contenido.='<p>Campo 1 <strong>'.$_POST['c1'].'</strong>';
 $contenido.='<p>Campo 2: <strong>'.$_POST['c2'].'</strong>';
  $contenido.='<p>Campo 3: <strong>'.$_POST['c3'].'</strong>';
 $contenido.='<p>Mesnsaje: <strong>'.$_POST['mensaje'].'</strong>';
$contenido .= '<hr />';
$contenido .= '</body></html>';

$mail->Body    = $contenido;
$mail->AddAttachment('archivos/'.$nuevonombre.'', $nuevonombre);  // optional name
// si todos los campos fueron completados enviamos el mail

$mail->Send();

$flag='ok';
$mensaje=' <div id="ok"> 
<strong>SUSCAMPOS FUERON ENVIADO</strong>


 </div>';
} else {
        
//si no todos los campos fueron completados se frena el envio y avisamos al usuario        
$flag='err';
$mensaje='<div id="error">- Los campos marcados con * son requeridos. '.$error_archivo.'</div>';

}
}
?>

<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Formulario con Valudacion</title>
</head>
<body>
<? echo $mensaje; /*mostramos el estado de envio del form */ ?>
<? if ($flag!='ok') { ?>
<form action="#" method="post" enctype="multipart/form-data">
Nombre
<input  <? if (isset ($flag) && $_POST['nombre']=='') { echo 'class="error"';} else {echo 'class="campo"';}?>  type="text" name="nombre" value="<? echo $_POST['nombre'];?>" >
<br>
Actual
<input  <? if (isset ($flag) && $_POST['direccion']=='') { echo 'class="error"';} else {echo 'class="campo"';} ?>   type="text" name="direccion" value="<? echo $_POST['direccion'];?>" >
<br>
Teléfono
<input <? if (isset ($flag) && $_POST['telefono']=='') { echo 'class="error"';} else {echo 'class="campo"';} ?>  type="text" name="telefono" value="<? echo $_POST['telefono'];?>" >
<br>
Correo electrónico:
<input  pattern="^[a-zA-Z0-9.!#$%'*+/=?^_`{|}~-]+@[a-zA-Z0-9-]+(?:\.[a-zA-Z0-9-]+)*$"  required  <? if (isset ($flag) && $_POST['email']=='') { echo 'class="error"';} else {echo 'class="campo"';} ?>  type="text" name="email" value="<? echo $_POST['email'];?>" >
<br>
¿Profesión?
<textarea  name="profesion" class="campo"></textarea>
<br>
Campo Uno:
<textarea  name="c1" class="campo"></textarea>
<br>
Campo DOS:
<textarea name="c2" class="campo"></textarea>
<br>
Campo Tres:
<textarea  name="c3" class="campo"></textarea>
<br>
Comentarios
<textarea  <? if (isset ($flag) && $_POST['mensaje']=='') { echo 'class="com-error"';} else {echo 'class="com"';} ?>  name="mensaje"><? echo $_POST['mensaje'];?></textarea>
<br>
<input class="boton" type="submit" name="enviar" value="Enviar" >
</form>

<? } ?>
#juanmafx 
</body>
</html>
