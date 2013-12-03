<?php

$direccion_envio= ' curso2013@abcequinoterapia.com.ar ' ; //la direccion a la que se enviara el email.
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
$contenido.='<p>Ha trabajado o trabaja en algún área relacionada a discapacidad: <strong>'.$_POST['c1'].'</strong>';
 $contenido.='<p>Realizo otros cursos de Equinoterapia: : <strong>'.$_POST['c2'].'</strong>';
  $contenido.='<p>Que experiencia tiene en equitación: <strong>'.$_POST['c3'].'</strong>';
 $contenido.='<p>Comentarios: <strong>'.$_POST['mensaje'].'</strong>';
$contenido .= '<hr />';
$contenido .= '</body></html>';

$mail->Body    = $contenido;
$mail->AddAttachment('archivos/'.$nuevonombre.'', $nuevonombre);  // optional name
// si todos los campos fueron completados enviamos el mail

$mail->Send();

$flag='ok';
$mensaje=' <div id="ok"> 
<strong>TU FORMULARIO FUE ENVIADO</strong>
<br>En tu mail encontrarás un correo de la fundación con toda la información del curso a dictarse el 20 21 22 Y 23 DE FEBRERO DEL  2014(4dias)<br>
<br>
<p>INFORMACION:</p>  <br>
<p>ATENNCION</p> <a href="infocurso.html">Mas informacion de como depositar </a>	<br>
<srong>El costo del curso de es de pesos 2000 para Argentinos </strong>.<br>
Dolares 320usd para extranejeros.<br><br>
Padres de miños especiales acceden a un descuento del 50%.<br>
<br>
Deposite solo 500 pesos, el resto el dia de la acreditación.
<strong>Datos de la cuenta para depositar</strong><br>
<h2>BANCO ROELA</h2><br>
FUNDACION CORDOBESA DE EQUINOTERAPIA:<br><br>
CUIT: 30707937471<br><br>
CUENTA: 1706/7<br><br>
CUENTA CORRIENTE EN PESOS<br><br>
SUCURSAL SAN MARTIN - 0005 <br><br>
CBU: 2470005610000000170674 <br><br>
<strong>Descuento</strong><br>
Hasta el 31 de enero el curso cuenta con un descuento del 15%<br>
Totalizando asi 1700 pesos.<br>
Conforme su insripcion depositando 500 Pesos. Asi accedera al descuento.

<br><br>
Desde Septiembre de 2013 la fundacion cordobesa de equinoterapia cuenta con <strong>CUENTA CORRIENTE </strong> en el Banco Roela.<br>
<br>
<br>
<br>
	
Recibiste en tu casilla de emial, el costo , cronograma y forma de pago del curso.<br>
Para estar al tanto de todas las actividades de la fundacion seguinos en facebook.<br>
<a  target="_blank" href="http://es-es.facebook.com/fundacionequinoterapia">www.facebook.com/fundacionequinoterapia</a><br>
Ante cualquier duda contactanos.

 </div>';
} else {
	
//si no todos los campos fueron completados se frena el envio y avisamos al usuario	
$flag='err';
$mensaje='<div id="error">- Los campos marcados con * son requeridos. '.$error_archivo.'</div>';

}
}
?>


<html>
<head>

</head>
<body>
<? echo $mensaje; /*mostramos el estado de envio del form */ ?>
<? if ($flag!='ok') { ?>
<br>
<form action="#" method="post" enctype="multipart/form-data">
<div align="left">

<table>
<tr>
<td>
<p><font color="red">*</font> <font color="black">Nombre</font></font> <br />
<input  <? if (isset ($flag) && $_POST['nombre']=='') { echo 'class="error"';} else {echo 'class="campo"';} ?> style="width:245px" class="campo" type="text" name="nombre" value="<? echo $_POST['nombre'];?>" /></p>
</td>
<td>
<p><font color="black">Dirección Actual</font> <br />
<input  <? if (isset ($flag) && $_POST['direccion']=='') { echo 'class="error"';} else {echo 'class="campo"';} ?> style="width:245px" class="campo" type="text" name="direccion" value="<? echo $_POST['direccion'];?>" /></p>
</td>
</tr>

<tr>
<td>
<p><font color="red">*</font>  <font color="black">Teléfono</font> <br />
<input <? if (isset ($flag) && $_POST['telefono']=='') { echo 'class="error"';} else {echo 'class="campo"';} ?> style="width:245px" class="campo" type="text" name="telefono" value="<? echo $_POST['telefono'];?>" /></p>
</td>
<td>
<p><font color="red">*</font>  <font color="black">Correo electrónico</font> <br />
<input  pattern="^[a-zA-Z0-9.!#$%'*+/=?^_`{|}~-]+@[a-zA-Z0-9-]+(?:\.[a-zA-Z0-9-]+)*$"  required	 <? if (isset ($flag) && $_POST['email']=='') { echo 'class="error"';} else {echo 'class="campo"';} ?> style="width:245px" class="campo" type="text" name="email" value="<? echo $_POST['email'];?>" /></p>
</td>
</tr>
</table>

<p> <font color="black">¿Profesión?</font> <br />
<textarea style="width:500px; height:40px;" name="profesion" class="campo"></textarea>

<p> <font color="black">Ha trabajado o trabaja en algún área relacionada a discapacidad:</font> <br />
<textarea style="width:500px; height:100px;" name="c1" class="campo"></textarea>
<br>

<p> <font color="black">Realizo otros cursos de Equinoterapia:</font> <br />
<textarea style="width:500px; height:100px;" name="c2" class="campo"></textarea>
<br>

<p> <font color="black">Que experiencia tiene en equitación:</font> <br />
<textarea style="width:500px; height:100px;" name="c3" class="campo"></textarea>
<br>


<p><font color="red">*</font>  <font color="black">Comentarios</font> <br />
<textarea  <? if (isset ($flag) && $_POST['mensaje']=='') { echo 'class="com-error"';} else {echo 'class="com"';} ?>style="width:500px; height:200px;" class="com" name="mensaje"><? echo $_POST['mensaje'];?></textarea></p>

<input class="boton" type="submit" name="enviar" value="Enviar" />
	</form>
	<? } ?>



</body>
</html>
