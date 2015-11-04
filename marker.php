<?php

class Marker extends Crime
{
    public function __construct()
	{
        parent::__construct();
	}

	public function marker($args = array())
	{
		$data = array();

        $args = $this->setArgs($args);

        $communities = Community::getCommunities();

        $data['communities'] = CommunityReport::getReportGroupsByDate($args);
        $data['communities'] = $this->formatGroups($data['communities']); // format communities so that each community lists the reported types and their colors in array form 

        $data['communitygroups'] = $this->formatMarkers($communities, $data['communities']);

        $data['reports'] = Report::getReportByDate($args);
        $data['reports'] = $this->formatReports($data['reports']);

		return $data;
	}

    public function formatMarkers($communities, $groups)
    {
        $data = array();

        foreach ($communities as $key => $value) 
        {
            $towns = explode(",", $value->town); // some items could fall into mutiple towns

            foreach ($towns as $town) 
            {
                if (!isset($groups[$value->id])) { continue; }
                
                $town = trim($town);

                if (!isset($groups[$town]))
                {
                    $data[$town]['lat'] = $groups[$value->id]['lat'];
                    $data[$town]['lng'] = $groups[$value->id]['lng'];
                } else if ($town == $groups[$value->id]['community'])
                {
                    $data[$town]['lat'] = $groups[$value->id]['lat'];
                    $data[$town]['lng'] = $groups[$value->id]['lng'];
                }

                $data[$town]['count'] = (isset($data[$town]['count'])) ? $data[$town]['count'] + $groups[$value->id]['count'] : $groups[$value->id]['count'];

                $types = array_keys($groups[$value->id]['type']);
                $types = $this->getTypes($types, $data[$town], 'types');
                $data[$town]['types'] = $types;

                $colors = $groups[$value->id]['color'];
                $colors = $this->getTypes($colors, $data[$town], 'color');
                $data[$town]['color'] = $colors;

                $data[$town]['communities'][$value->id] = $groups[$value->id];
            }
        }

        return $data;
    }

    public function getTypes($data, $types, $index)
    {
        if (isset($types[$index]))
        {
            foreach ($types[$index] as $key => $value)
            {
                if (!in_array($value, $data))
                {
                    $data[] = $value;
                }
            }
        } 

        return $data;
    }

    public function getMarkerImgId($markers)
    {
        $data = array();

        foreach ($markers as $key => $value) 
        {
            $data[$value->color][] = intval($value->count);
        }

        return $data;
    }

    // http://www.devinrolsen.com/google-maps-marker-icon-counter/marker-maker.php?fontType=ARIAL&fontSize=9&x=13&y=13&r=0&color=255,255,255&image=map-pin-kc-temp.png&text=1
    public static function createMarker($args, $aws)
    {
        $saved = false;
        $image = $GLOBALS['paths']['public'].'/img/dot_'.$args['color'].'.png';

        $img = self::createPNG($image, $args);

        imagealphablending($img, false);
        imagesavealpha($img, true);

        ob_start(); 
            imagepng ($img);
            $markerImg = ob_get_contents();
            imagedestroy($img);
        ob_end_clean(); 
        
        $saved = $aws->push_to_s3($markerImg, $args);

        if ($saved)
        {
            MarkerImg::setMarkers($args);
        }

        return $markerImg;
    }
    
    public static function createPNG($imgname, $args)
    {
        list($r, $g, $b) = array('255', '255', '255');
        $fontSize = intval($args['fontSize']);
        $fontType = $GLOBALS['paths']['public'].'/fonts/crime.ttf';
        $x = intval($args['x']);
        $y = intval($args['y'])+$fontSize;
        $rot = intval(0);
        $text = $args['text'];
        $im = imagecreatefrompng($imgname);
        $fontColor = imagecolorallocate($im, $r, $g, $b);

        // find the size of the text
        $box = ImageTTFBBox($fontSize, $rot, $fontType, $text);
        $xr = abs(max($box[2], $box[4]));
        $yr = abs(max($box[5], $box[7]));

        // compute centering
        $x = intval((ImageSX($im) - $xr) / 2);
        $y = intval((ImageSY($im) + $yr) / 2);
     
        ImageTTFText($im,$fontSize,$rot,$x,$y,$fontColor,$fontType,$text);
     
        return $im;
    }
}

?>
