<?php
  
    function comprobarRespuestas($sNumeroPreguntas,$sRespuestasCorrectas,$sRespuestasEstudiante,$objetivo,$config){
        $sAcierta=0;
        $sAciertos=0;
        $sAciertoDoble=0;

        for ($i = 0; $i <=  $sNumeroPreguntas-1; $i++) {
            $auxE=$sRespuestasEstudiante[$i];
            $auxC=$sRespuestasCorrectas[$i];

            if(is_array($auxC) && is_array($auxE)){
                if(array_count_values($auxC)==array_count_values($auxE)){ //esto lo he hecho para que si hay una respuesta solo que la de como mala
                    for ($j = 0; $j <count($auxE); $j++) {     
                        if(in_array($sRespuestasEstudiante[$i][$j],$sRespuestasCorrectas[$i])){
                            $sAcierta=1;
                        }else{
                            $sAcierta=0;
                        }
                    }
                }else{
                    $sAcierta=0;
                }
            }else{
                if (in_array($auxC,$auxE)){
                    $sAcierta=1;
                }else{
                    $sAcierta=0;
                }
            }

            if($sAcierta==1){
                $sAciertos++;
            }
            $sPorcentajes= round(($sAciertos*100/$sNumeroPreguntas),2);
            $sPorcentajes1= round(($sAciertos*10/$sNumeroPreguntas),2);  
        }

        $datos[]=array('aciertos'=>"$sAciertos",'porcentajes'=>"$sPorcentajes",'porcentajes1'=>"$sPorcentajes1");
        $total=[$sRespuestasCorrectas, $datos];
        $respuestaFinal =json_encode($total);
        sendGrade($sPorcentajes,$objetivo,$config);
        sendEvent($sPorcentajes,$config);
        echo $respuestaFinal;
    }
    
    function sendGrade($nota,$evaluacion,$config){   
        $token = $config->get("TOKEN");
                
        $postfield = '{';
        $postfield = $postfield . '"id": "' . $evaluacion->id . '"';
        $postfield = $postfield . ',"nota": "' . $nota . '"';
        $postfield = $postfield . '}';

        $curl = curl_init();
    
        curl_setopt_array($curl, array(
        CURLOPT_URL => getenv('setevaluation'),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'PUT',
        CURLOPT_POSTFIELDS => $postfield,
        CURLOPT_HTTPHEADER => array(
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json'
        ),
        ));
    
        $response = curl_exec($curl);
        $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);   
        http_response_code($httpcode);
                    
        if ($httpcode==201){                
            $gradetosend = (string)($nota / 100);		
            $gradetosend = str_replace(",",".",$gradetosend);
            $resultado = 0;	
            $debug_log = array();
            $retval =  $config->sendGrade($gradetosend);
            if ( $retval != true ) {
                http_response_code(404);    
            }else{
                $config->set("GRADE", $nota);
                $config->setEvaluationObjective($nota);
                $config->setSession();
            }
        }else{
            http_response_code(404);
        }
    }

    function sendEvent($nota,$config){
        $type="Objective";
        $token = $config->get("TOKEN");    
                
        $timestamp = microtime(TRUE);
        $objDateTime = new DateTime('NOW');
        $objDateTimeS = $objDateTime->format('Y-m-d H:i:s');                
        $profile = $config->get("ROLES");
                   
        $postfield = '{';
        $postfield = $postfield . '"date": "' .$objDateTimeS . '"';
        $postfield = $postfield . ',"profile": "' .$profile . '"';
        $postfield = $postfield . ',"timestamp": "' .$timestamp . '"';
        $postfield = $postfield . ',"percentage": "' .$nota . '"';
        $postfield = $postfield . ',"type": "' .$type . '"';
        $postfield = $postfield . ',"element": "Evaluation"';
        $postfield = $postfield . ',"notes": "Evaluation"';
        $postfield = $postfield . ',"description": ""';
        $postfield = $postfield . ',"unit_type": "Evaluation"';
        $postfield = $postfield . '}';

        $curl = curl_init();

        curl_setopt_array($curl, array(
        CURLOPT_URL => getenv('laevent'),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',  
        CURLOPT_POSTFIELDS => $postfield,
        CURLOPT_HTTPHEADER => array(
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json'
        ),
        ));

        $response = curl_exec($curl);
        $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);   
        http_response_code($httpcode);
    }
?>