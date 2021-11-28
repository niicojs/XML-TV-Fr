<?php
class Utils
{
    public static function generateFilePath($xmlpath,$channel,$date)
    {
        return $xmlpath.$channel."_".$date.".xml";
    }

    public static function validateXML($PATH) {
        echo "\e[34m[EXPORT] \e[39mValidation du fichier XML...\n";
        @$xml = XMLReader::open($PATH);

        $xml->setParserProperty(XMLReader::VALIDATE, true);

        if($xml->isValid())
        {
            echo "\e[34m[EXPORT] \e[32mXML valide\e[39m\n";
        } else {
            echo "\e[34m[EXPORT] \e[31mXML non valide\e[39m\n";
        }
    }

    public static function gzCompressXML($OUTPUT_PATH) {
        echo "\e[34m[EXPORT] \e[39mCompression du XMLTV en GZ...\n";
        $got = file_get_contents($OUTPUT_PATH.'/xmltv.xml');
        $got1 = gzencode($got,true);
        file_put_contents($OUTPUT_PATH.'/xmltv.xml.gz',$got1);
        echo "\e[34m[EXPORT] \e[39mGZ : \e[32mOK\e[39m\n";
    }

    public static function zipCompressXML($OUTPUT_PATH) {
        echo "\e[34m[EXPORT] \e[39mCompression du XMLTV en ZIP...\n";
        $zip = new ZipArchive();
        $filename = $OUTPUT_PATH."/xmltv.zip";

        if ($zip->open($filename, ZipArchive::CREATE)!==TRUE) {
            echo "\e[34m[EXPORT] \e[39mZIP : \e[31mHS\e[39m\n";
        } else {
            echo "\e[34m[EXPORT] \e[39mZIP : \e[32mOK\e[39m\n";
        }
        $zip->addFile($OUTPUT_PATH."/xmltv.xml", "xmltv.xml");
        $zip->close();

    }

    public static function getChannelsEPG($classes_priotity, $XML_PATH, $CONFIG, $CLASS_PREFIX) {
        echo "\e[95m[EPG GRAB] \e[39mRécupération du guide des programmes\n";
        $logs = array('channels'=>array(), 'xml'=>array(),'failed_providers'=>array());
        $channels = json_decode(file_get_contents('channels.json'),true);
        $channels_key = array_keys($channels);
        foreach($channels_key as $channel)
        {
            if(isset($channels[$channel]["priority"]) && count($channels[$channel]["priority"]) > 0)
            {
                $priority = $channels[$channel]['priority'];
            } else {
                $priority = $classes_priotity;
            }
            for($i=-1;$i<$CONFIG["days"];$i++)
            {
                $date = date('Y-m-d',time()+86400*$i);
                echo "\e[95m[EPG GRAB] \e[39m".$channel." : ".$date;
                if(!file_exists(Utils::generateFilePath($XML_PATH,$channel,$date))) {
                    $success = false;
                    foreach ($priority as $classe) {
                        if(!class_exists($classe))
                            break;
                        if(!isset(${$CLASS_PREFIX.$classe}))
                            ${$CLASS_PREFIX.$classe} = new $classe($XML_PATH);
                        if(${$CLASS_PREFIX.$classe}->constructEPG($channel,$date))
                        {
                            $logs["channels"][$date][$channel]['success'] = true;
                            echo " | \e[32mOK\e[39m - ".$classe.chr(10);
                            $logs["channels"][$date][$channel]['provider'] = $classe;
                            break;
                        }
                        $logs["channels"][$date][$channel]['failed_providers'][] = $classe;
                        $logs["channels"][$date][$channel]['success'] = false;
                        $logs["failed_providers"][$classe] = true;
                    }
                    if(!$logs["channels"][$date][$channel]['success'])
                        echo " | \e[31mHS\e[39m".chr(10);
                } else {
                    $logs["channels"][$date][$channel]['provider'] = 'Cache';
                    echo " | \e[33mOK \e[39m- Cache".chr(10);
                    $logs["channels"][$date][$channel]['success'] = true;

                }
            }
        }
        echo "\e[95m[EPG GRAB] \e[39mRécupération du guide des programmes terminée...\n";
        $log_path = 'logs/logs'.date('YmdHis').'.json';
        echo "\e[36m[LOGS] \e[39m Export des logs vers $log_path\n";
        file_put_contents($log_path,json_encode($logs));
    }

    public static function clearOldXML($CONFIG) {
        $xmltv = glob($CONFIG['output_path'].'/xmltv*');
        foreach($xmltv as $file)
        {
            if(time()-filemtime($file) >= 86400*$CONFIG["xml_cache_days"])
                unlink($file);

        }
    }

    public static function moveOldXML($CONFIG) {
        foreach(["xml","zip","xml.gz"] as $ext) {

            if(file_exists($CONFIG['output_path']."/xmltv.$ext"))
            {
                rename($CONFIG['output_path']."/xmltv.$ext",$CONFIG['output_path']."/xmltv_".date('Y-m-d_H-i-s',filemtime($CONFIG['output_path']."/xmltv.$ext")).".$ext");
            }
        }
    }

    public static function clearXMLCache($CONFIG, $XML_PATH) {
        $files = glob($XML_PATH.'*');
        foreach($files as $file){
            if(time()-filemtime($file) >= 86400 * $CONFIG['cache_max_days'])
                unlink($file);
        }
    }

    public static function clearEPGCache($CONFIG) {

        $tmp_files = glob_recursive('epg/*');
        foreach($tmp_files as $file)
        {
            if(!is_dir($file) && time() - filemtime($file) >= ($CONFIG["cache_max_days"])*86400)
            {
                unlink($file);
            }
        }
    }

    public static function generateXML($CONFIG, $XML_PATH) {

        echo "\e[34m[EXPORT] \e[39mGénération du XML...\n";
        $channels = json_decode(file_get_contents('channels.json'),true);
        $filepath = $CONFIG['output_path']."/xmltv.xml";
        $out = fopen($filepath, "w");
        fwrite($out,'<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE tv SYSTEM "xmltv.dtd">

<tv source-info-url="https://github.com/racacax/XML-TV-Fr" source-info-name="XML TV Fr" generator-info-name="XML TV Fr" generator-info-url="https://github.com/racacax/XML-TV-Fr">
  ');
        foreach($channels as $key => $channel)
        {
            @$icon = $channel['icon'];
            @$name = $channel['name'];
            if(!isset($name))
                $name = $key;
            fwrite($out,'<channel id="'.$key.'">
    <display-name>'.$name.'</display-name>
    <icon src="'.$icon.'" />
  </channel>'.chr(10));
        }
        $files = glob($XML_PATH.'*');
        foreach($files as $file){
            $in = fopen($file, "r");
            while ($line = fgets($in)){
                fwrite($out, $line);
            }
            fclose($in);
        }
        fwrite($out,'</tv>');
        fclose($out);

        echo "\e[34m[EXPORT] \e[39mGénération du XML terminée\n";
    }

}