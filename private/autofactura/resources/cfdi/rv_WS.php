<?php
/******************************************************************\
|     efectosFiscalesWS.php, version: 1.0   (20 Mayo 2014)         |
|           Website: efectosfiscales.mx                            |
|             Copyright (C) 3eMexico 2014                          |
\******************************************************************/
require_once("nusoap/lib/nusoap.php");
class RV_WS{
	var $config;
	function __construct($configuraciones ){
		$this->config = $configuraciones;
	}
	/*-------------------------------------------------------------------*/
	/*         FUNCIONES DE WEBSERVICE PARA CONSULTA REALVIRTUAL         */
	/*-------------------------------------------------------------------*/

	public function wsGetTicket(){	
		try{	
			$client = new nusoap_client($this->config['wsdl'], 'WSDL');
			$err = $client->getError();
			if ($err) 
    			return $err;   		
    		$params= array( 'username' => $this->config['username'], 
    						'password' => $this->config['password'],
    						'xml' => $this->config['xml']);
			$result = $client->call('wsGetTicket', $params, '', '', false, true);
			return  $result;	
		}catch (SoapFault $e){
			return $e->getMessage();
		}
	}
	
	public function ProbarTimbradoCFDI33Principal(){	
		try{	
			$client = new nusoap_client($this->config['wsdl'], 'WSDL');
			$err = $client->getError();
			if ($err) 
    			return $err;   		
    		$params= array( 'username' => $this->config['username'], 
    						'password' => $this->config['password'],
    						'xml' => $this->config['xml']);
			$result = $client->call('ProbarTimbradoCFDI33Principal', $params, '', '', false, true);
			return  $result;	
		}catch (SoapFault $e){
			return $e->getMessage();
		}
	}
	
	public function ProbarTimbradoCFDI33Secundario(){	
		try{	
			$client = new nusoap_client($this->config['wsdl'], 'WSDL');
			$err = $client->getError();
			if ($err) 
    			return $err;   		
    		$params= array( 'username' => $this->config['username'], 
    						'password' => $this->config['password'],
    						'xml' => $this->config['xml']);
			$result = $client->call('ProbarTimbradoCFDI33Secundario', $params, '', '', false, true);
			return  $result;	
		}catch (SoapFault $e){
			return $e->getMessage();
		}
	}
	
	public function wsGetTicketNO(){	
		try{	
			$client = new nusoap_client($this->config['wsdl'], 'WSDL');
			$err = $client->getError();
			if ($err) 
    			return $err;   		
    		$params= array( 'username' => $this->config['username'], 
    						'password' => $this->config['password'],
    						'xml' => $this->config['xml']);
			$result = $client->call('wsGetTicketNO', $params, '', '', false, true);
			return  $result;	
		}catch (SoapFault $e){
			return $e->getMessage();
		}
	}
	
	public function wsGetTicketSimple(){		
		try{	
			$client = new nusoap_client($this->config['wsdl'], 'WSDL');
			$err = $client->getError();
			if ($err) 
    			return $err;   		
    		$params= array( 'username' => $this->config['username'], 
    						'password' => $this->config['password'],
    						'xml' => $this->config['xml']);
			$result = $client->call('wsGetTicketSimple', $params, '', '', false, true);
			return  $result;	
		}catch (SoapFault $e){
			return $e->getMessage();
		}
	}

	public function wsPpoCfdTimbre_Cn(){		
		try{
			$params = $this->config;
			$soap_client = new SoapClient($this->miwsdl, $this->config);
			$respuesta = $soap_client->__soapCall('wsPpoCfdTimbre_Cn', array('parametros' => $params));
			return $respuesta;
		}catch (SoapFault $e){
			//imprimir el error enviado por el servicio.
			return $e->getMessage();
		}
	}
	
	public function wsRetryCfdis(){	
		try{
			$params = $this->config;
			$soap_client = new SoapClient($this->miwsdl, $this->config);
			$respuesta = $soap_client->__soapCall('wsRetryCfdis', array('parametros' => $params));
			return $respuesta;
		}catch (SoapFault $e){
			//imprimir el error enviado por el servicio.
			return $e->getMessage();
		}
	}
	
	public function wsSchemaCfd(){
		try{	
			$client = new nusoap_client($this->config['wsdl'], 'WSDL');
			$err = $client->getError();
			if ($err) 
    			return $err;   		
    		$params= array( 'username' => $this->config['username'], 
    						'password' => $this->config['password'],
    						'xml' => $this->config['xml']);
			$result = $client->call('wsSchemaCfd', $params, '', '', false, true);
			return  $result;	
		}catch (SoapFault $e){
			return $e->getMessage();
		}
	}

	public function wsTestCfd(){	
		try{	
			$client = new nusoap_client($this->config['wsdl'], 'WSDL');
			$err = $client->getError();
			if ($err) 
    			return $err;   		
    		$params= array( 'username' => $this->config['username'], 
    						'password' => $this->config['password'],
    						'xml' => $this->config['xml']);
			$result = $client->call('wsTestCfd', $params, '', '', false, true);
			return  $result;	
		}catch (SoapFault $e){
			return $e->getMessage();
		}
	}

	public function wsCancelTicket(){	
		try{	
			$client = new nusoap_client($this->config['wsdl'], 'WSDL');
			$err = $client->getError();
			if ($err) 
    			return $err;   		
    		$params= array( 'username' => $this->config['username'], 
    						'password' => $this->config['password'],
    						'cancel_xml' => $this->config['cancel_xml']);
			$result = $client->call('wsCancelTicket', $params, '', '', false, true);
			return  $result;	
		}catch (SoapFault $e){
			return $e->getMessage();
		}
	}
		
	public function wsCancelTicketExtended(){		
		try{
			$params = $this->config;
			$soap_client = new SoapClient($this->miwsdl, $this->config);
			$respuesta = $soap_client->__soapCall('wsCancelTicketExtended', array('parametros' => $params));
			return $respuesta;
		}catch (SoapFault $e){
			//imprimir el error enviado por el servicio.
			return $e->getMessage();
		}
	}
	
	public function wsGetAcuse(){			
		try{
			$params = $this->config;
			$soap_client = new SoapClient($this->miwsdl, $this->config);
			$respuesta = $soap_client->__soapCall('wsGetAcuse', array('parametros' => $params));
			return $respuesta;
		}catch (SoapFault $e){
			//imprimir el error enviado por el servicio.
			return $e->getMessage();
		}
	}

	public function wsGetStatusSat(){			
		try{
			$params = $this->config;
			$soap_client = new SoapClient($this->miwsdl, $this->config);
			$respuesta = $soap_client->__soapCall('wsGetStatusSat', array('parametros' => $params));
			return $respuesta;
		}catch (SoapFault $e){
			//imprimir el error enviado por el servicio.
			return $e->getMessage();
		}
	}
	/*-------------------------------------------------------------------*/
	/*   TERMINA FUNCIONES DE WEBSERVICE PARA CONSULTA EFECTOSFISCALES   */
	/*-------------------------------------------------------------------*/
	
	
	public function wsGetCredit(){			
		try{			 
			$client = new nusoap_client($this->config['wsdl'], 'WSDL');

			$err = $client->getError();
			if ($err) { return $err; }
    		$params= array( 'username' => $this->config['username'], 
    						'password' => $this->config['password']);
			$result = $client->call('wsGetCredit', $params, '', '', false, true);

			if ($client->fault) {
				return $result;
			} else {
				$err = $client->getError();
				if ($err) {
					return '<h2>Error</h2><pre>' . $err . '</pre>';
				} else {
					return $result;
				}
			}
		}catch (SoapFault $e){
			//imprimir el error enviado por el servicio.
			return $e->getMessage();
		}
	}
	
	
	public function wsGetConsumos(){			
		try{			 
			$client = new nusoap_client($this->config['wsdl'], 'WSDL');

			$err = $client->getError();
			if ($err) { return $err; }
    		$params= array( 'username' => $this->config['username'], 
    						'password' => $this->config['password']);
			$result = $client->call('wsGetConsumos', $params, '', '', false, true);

			if ($client->fault) {
				return $result;
			} else {
				$err = $client->getError();
				if ($err) {
					return '<h2>Error</h2><pre>' . $err . '</pre>';
				} else {
					return $result;
				}
			}
		}catch (SoapFault $e){
			//imprimir el error enviado por el servicio.
			return $e->getMessage();
		}
	}
	public function wsGetDisponibles(){			
		try{			 
			$client = new nusoap_client($this->config['wsdl'], 'WSDL');

			$err = $client->getError();
			if ($err) { return $err; }
    		$params= array( 'username' => $this->config['username'], 
    						'password' => $this->config['password']);
			$result = $client->call('wsGetDisponibles', $params, '', '', false, true);

			if ($client->fault) {
				return $result;
			} else {
				$err = $client->getError();
				if ($err) {
					return '<h2>Error</h2><pre>' . $err . '</pre>';
				} else {
					return $result;
				}
			}
		}catch (SoapFault $e){
			//imprimir el error enviado por el servicio.
			return $e->getMessage();
		}
	}
}
?>

  