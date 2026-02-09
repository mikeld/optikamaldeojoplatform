<?php

class ClienteHelper 
{
    /**
     * Genera un enlace de llamada telefónica con el número proporcionado.
     *
     * @param string $telefono El número de teléfono del cliente.
     * @return string Enlace de llamada con el formato tel:+34XXXXXXXXX
     */
    public static function generarEnlaceLlamada($telefono) 
    {
        // Eliminar cualquier carácter no numérico del teléfono
        $telefono_limpio = preg_replace('/\D/', '', $telefono);
        
        // Retornar el enlace de llamada
        return "tel:" . $telefono_limpio;
    }

    /**
     * Genera un enlace de correo electrónico con el email proporcionado y el mensaje predeterminado.
     *
     * @param string $email El correo electrónico del cliente.
     * @param string $nombreCliente El nombre del cliente.
     * @param string $referenciaPedido La referencia del pedido.
     * @return string Enlace de correo (mailto) con asunto y cuerpo del mensaje
     */
    public static function generarEnlaceEmail($email, $nombreCliente, $referenciaPedido) 
    {
        // Mensaje predeterminado del correo
        $asunto = "Pedido no recibido";
        $mensaje = "Hola $nombreCliente, no nos ha llegado a tiempo el pedido $referenciaPedido.";
        
        // Codificar los parámetros del correo para evitar errores en el URL
        $asunto_codificado = rawurlencode($asunto);
        $mensaje_codificado = rawurlencode($mensaje);

        // Retornar el enlace de email
        return "mailto:$email?subject=$asunto_codificado&body=$mensaje_codificado";
    }
}
