<?php
/*
Author
*/

namespace Study\EmailValidator\Service;

use Shuchkin\SimpleXLSXGen;

class DDXParser
{

    private string $uploadDir;
    private string $pathToXLS;
    private string $baseDir;
    private string $url;
    private array $line_data;

    private int $track_index;





    public function __construct(

    )
    {
        $this->uploadDir = dirname( dirname( __DIR__ ) ) . '/upload';
        $this->pathToXLS =  dirname( dirname( __DIR__ ) ) .'/storage';
        $this->baseDir = dirname( dirname( __DIR__ ) );
        $this->line_data = [];
        //$errors_data = [];
        $this->track_index = 0;
        $this->url = "https://tkt-school.kz";
       // $this->url = "http://mysite.com";
    }
     /* todo
      * 1. разбить на транши
      * 2. проверить расширение, если zip, то разархивировать
      * 3.
    * */


    public function getFoldersList() {
        $filename = $this->pathToXLS.'/folders.csv';

        $folders = scandir($this->uploadDir, SCANDIR_SORT_NONE);
        $folders =  array_filter($folders, function ($el){
            return  !in_array($el, ['.', '..', 'test']) && is_dir($this->uploadDir.'/'.$el);;
        });

        $fp = fopen($filename, 'w');  //w
        fputcsv($fp, $folders);
        fclose($fp);

        return count($folders);
    }

    public  function validate()
    {
        $filename = $this->pathToXLS.'/folders.csv';
        if (($open = fopen($filename, "r")) !== false) {
            while (($data = fgetcsv($open, null, ",")) !== false) {
                $array[] = $data;
            }

            fclose($open);
        }
        $folders = array_slice($array[0], 0, 20);

        foreach ($folders as $folder)
        {
            $file_info = $this->findXMLFileInFolder($folder);
            $xml_file_name = $file_info[0];

            try {
                    $file = \XMLReader::open($xml_file_name);
                    $file->setSchema("http://ddex.net/xml/ern/341/release-notification.xsd");
                    //$file->setParserProperty(\XMLReader::VALIDATE, true);
                    //print $file;
                    if($file->isValid()) {
                        $xml = simplexml_load_file($xml_file_name);
                    }
                    else {
                            $this->errors_data[] = $xml_file_name;
                            continue;
                        }

            }
            catch (\Exception $exception)
            {
                print ($exception->getMessage());
            }



        }

    }
    public function parse($offset, $len): ?array
    {


        // $xml = $path."/test/20240405141604533/5057827062181_20240405141604/5057827062181.xml";
        $filename = $this->pathToXLS.'/folders.csv';
        if(!file_exists($filename))
            return ['errors' => ['Нет файла со списком! Сначала сформируйте файл!']];

        if (($open = fopen($filename, "r")) !== false) {
            while (($data = fgetcsv($open, null, ",")) !== false) {
                $array[] = $data;
            }

            fclose($open);
        }

        
        $offset = (int)$offset ?? 0;
        $len = (int)$len ?? max($array[0]);
        $folders =  array_slice($array[0], $offset, $len);

        foreach ($folders as $folder)
        {
            $file_info = $this->findXMLFileInFolder($folder);
            $xml_file_name = $file_info[0];
            $xml = simplexml_load_file($xml_file_name);

            $this->collectMetaDataForOneDDX($xml, $this->url.'/upload/'.$file_info[1]);

        }


        $columnNames = ['track_id', 'upc', 'isrc', 'track_number', 'track_name','genre_name', 'album_id','album_name', 'artist_name', 'composer','lyricist', 'explicit', 'duration', 'label', 'author_right', 'author_right_id', 'related_right', 'related_right_id','countries', 'release_date', 'path_to_resource'];
        $this->arrayToCsv($this->line_data, $offset ? null : $columnNames, 'metadata.csv', $offset ? 'a' : 'w' );

        array_unshift($this->line_data,  $columnNames);
        $this->arrayToXLSX($this->line_data, $this->getResultFileName($offset, $len));


        $result = [
            'url' => $this->url.'/'.$this->getResultFileName($offset, $len),
            'url_csv' => $this->url.'/storage/metadata.csv',
            'offset' => $offset,
            'len' => $len+$offset
        ];

        return  $result;
    }

    private function getResultFileName(int $offset, int $len)
    {
        return  'storage/metadata_'.$offset.'_'.$len+$offset.'.xlsx';
    }

    private function findXMLFileInFolder(string $mainFolder_name): array
    {
        $folder = $this->uploadDir.'/'.$mainFolder_name;
        $dir = scandir($folder);
        $dir =  array_filter($dir, function ($el) use ($folder){
            return !in_array($el, ['.', '..']) && is_dir($folder.'/'.$el);
        });
        $item = array_pop($dir);
        $parentFolder = $mainFolder_name.'/'.$item;
        $folder = $folder.'/'.$item;

        //ищем xml file
        $dir = scandir($folder);
        $dir =  array_filter($dir, function ($el) use ($folder){
            return !in_array($el, ['.', '..']) && is_file($folder.'/'.$el) ;
        });
        $xml =  $folder.'/'.array_pop($dir);

        return [$xml, $parentFolder ];
    }
    public static function simpleXmlToArray(?\SimpleXMLElement $xml): array|string {
        if ($xml === null) {
            return [];
        }

        $result = [];
        foreach ($xml->attributes() as $attrName => $attrValue) {
            $result['@attributes'][$attrName] = (string) $attrValue;
        }

        foreach ($xml->children() as $childName => $childElement) {
            $childArray = self::simpleXmlToArray($childElement);

            if (!isset($result[$childName])) {
                $result[$childName] = $childArray;
                continue;
            }
            //todo is_list = array_is_list
            if (!is_array($result[$childName]) || !is_list($result[$childName])) {
                $result[$childName] = [$result[$childName]];
            }
            $result[$childName][] = $childArray;
        }

        $textContent = trim((string) $xml);
        if (empty($result)) {
            return $textContent;
        }
        if ($textContent !== '') {
            $result['@value'] = $textContent;
        }

        return $result;
    }
    private function collectMetaDataForOneDDX($xml2, string $pathToResource){


        $rights = $this->getRights($xml2->WorkList);

        /**
         * Определяем информацию об альбоме
         */

        $realease = $xml2->ReleaseList->Release[0];
        if($realease->ReleaseType  == 'Album' ) {
            $upc  = (string) $xml2->ReleaseList->Release->ReleaseId->ICPN;
            $album_id  = (string)$xml2->ReleaseList->Release->ReleaseId->ProprietaryId;
            $album_name  = (string)$xml2->ReleaseList->Release->ReferenceTitle->TitleText;
            $release_date  = (string)$xml2->ReleaseList->Release->ReleaseDetailsByTerritory->OriginalReleaseDate;
        }
        elseif($realease->ReleaseType  == 'Single' ) {
            $upc  = (string) $xml2->ReleaseList->Release->ReleaseId->ICPN;
            $album_id  = (string)$xml2->ReleaseList->Release->ReleaseId->ProprietaryId;
            $album_name  = (string) 'Single'; //$xml2->ReleaseList->Release->ReferenceTitle->TitleText;
            $release_date  = (string)$xml2->ReleaseList->Release->ReleaseDetailsByTerritory->OriginalReleaseDate;
        }

        $resource_list  = $xml2->ResourceList->SoundRecording;
        $i = $this->track_index;
        $k = 0;
        foreach($resource_list as $resource)
        {
            //$realease->ReleaseType == 'Single' ? 1 :
            if(isset($resource->SoundRecordingDetailsByTerritory->TechnicalSoundRecordingDetails)) {
                $fileName = (string)$resource->SoundRecordingDetailsByTerritory->TechnicalSoundRecordingDetails->File->FileName ;
                $track_number = substr( $fileName, strlen( $upc ) + 3, strpos( $fileName, '.' ) - (strlen( $upc ) + 3) );
            }
            else
            {
                $track_number = 0;
            }

            $this->line_data[$i]['track_id'] = (string)$resource->SoundRecordingId->ProprietaryId;
            $this->line_data[$i]['upc'] = $upc;
            $this->line_data[$i]['isrc'] = (string)$resource->SoundRecordingId->ISRC;
            $this->line_data[$i]['track_number']  = $track_number;
            $this->line_data[$i]['track_name'] = (string)$resource->ReferenceTitle->TitleText;
            $this->line_data[$i]['genre_name'] = (string)$resource->SoundRecordingDetailsByTerritory->Genre->GenreText;

            $this->line_data[$i]['album_id'] = $album_id;
            $this->line_data[$i]['album_name'] = $album_name;

            //artists

            $artists  = self::simpleXmlToArray($resource->SoundRecordingDetailsByTerritory)['DisplayArtist'];
            $artist = $this->getArtists($artists);
            $this->line_data[$i]['artist_name'] = $artist['artist_name'];
            $this->line_data[$i]['Composer'] = $artist['composer'];
            $this->line_data[$i]['Lyricist'] = $artist['lyricist'];

            //explicit = ParentalWarningType
            $this->line_data[$i]['explicit'] = (string)$resource->SoundRecordingDetailsByTerritory->ParentalWarningType;
            $this->line_data[$i]['duration2'] = $this->formatTime((string)$resource->Duration);
            $this->line_data[$i]['label'] = (string)$resource->SoundRecordingDetailsByTerritory->LabelName;

            // author_right
            $this->line_data[$i]['author_right']  = $rights[$k]['RightsAdministrator'];
            $this->line_data[$i]['author_right_id']  = ' ';
            $this->line_data[$i]['related_right']  = $rights[$k]['RightsController'];
            $this->line_data[$i]['related_right_id']  = ' ';

            // countries
            $this->line_data[$i]['countries'] = (string)$resource->SoundRecordingDetailsByTerritory->TerritoryCode;
            $this->line_data[$i]['release_date'] = $release_date;
            $this->line_data[$i]['path'] = $pathToResource;

            // break;
            $i++;
            $k++;

        }
        $this->track_index = $i;

    }
    private function formatTime(string $duration){
        $m_pos = strpos($duration, 'M');
        $min = substr($duration,2, $m_pos-2);
        $sec = substr($duration,$m_pos+1, -1);
        return "$min:$sec";
    }
    private function getArtists(array $artists) {
        $separator = ",";
        $composer =  $lyricist =  $artist_name = null;
        $result = [];

        if(isset($artists['ArtistRole']) && isset($artists['PartyName']['FullName']) ){
            $fullName =  $artists['PartyName']['FullName'];
            if((string)$artists['ArtistRole'] == 'Composer') {
                $composer .= $composer ? $separator.$fullName : $fullName;
            }
            else if((string)$artists['ArtistRole'] == 'Lyricist') {
                $lyricist .= $lyricist ? $separator.$fullName : $fullName; ;
            }
            else if((string)$artists['ArtistRole']== 'MainArtist') {
                $artist_name .= $artist_name ? $separator.$fullName : $fullName; ;
            }
        }
        else {

            foreach ($artists as $artist ){

                $fullName =  $artist['PartyName']['FullName'];
                if((string)$artist['ArtistRole'] == 'Composer') {
                    $composer .= $composer ? $separator.$fullName : $fullName;
                }
                else if((string)$artist['ArtistRole'] == 'Lyricist') {
                    $lyricist .= $lyricist ? $separator.$fullName : $fullName; ;
                }
                else if((string)$artist['ArtistRole']== 'MainArtist') {
                    $artist_name .= $artist_name ? $separator.$fullName : $fullName; ;
                }
            }

        }
        return [
            'composer' => $composer,
            'lyricist' => $lyricist,
            'artist_name' => $artist_name,
        ];
    }
    private function getRights($worklist){
        $all_rights = [];
        $musicalWorks  = self::simpleXmlToArray($worklist)['MusicalWork'];


        $i = 0;
        if(count($musicalWorks) > 1){
            foreach ($musicalWorks as $musicalWork){
                $rights =  $musicalWork['RightShare']['RightsController'];
                foreach ($rights as $right){
                    $all_rights[$i][$right['RightsControllerRole']] = $right['RightSharePercentage'];

                }
                $i++;
            }
        }
        else{
            $rights =  $musicalWorks['RightShare']['RightsController'];
            foreach ($rights as $right){
                $all_rights[$i][$right['RightsControllerRole']] = $right['RightSharePercentage'];

            }
        }

        return $all_rights;
    }

    private function arrayToXLSX(array $files_meta, string $fileName)
    {
        //todo - проверить есть ли файл
        // имя файла
        $xlsx =  SimpleXLSXGen::fromArray( $files_meta );
        $xlsx->saveAs($this->baseDir.'/'.$fileName);
        //$this->getResultFileName($offset, $len)
    }
    private  function arrayToCsv($array,  ?array $columnTitles, $filename = 'metadata.csv', $mode = 'w') {
        $filename = $this->pathToXLS.'/'.$filename;
        $fp = fopen($filename, $mode);  //w

      /*
        if($isColumnTitles){
            $keys = array_shift($array);   //array_keys(get_object_vars($array[0]));
            fputcsv($fp, $keys);
        }
*/
        if(!empty($columnTitles)){
           // $keys = array_shift($array);   //array_keys(get_object_vars($array[0]));
            fputcsv($fp, $columnTitles);
        }

        foreach ($array as $entry) {
            //$values = array_values(get_object_vars($entry));
            $values = $entry;
            fputcsv($fp, $values);
        }

        fclose($fp);
    }
    private function unzip_file($file_path, $dest ){
        $zip = new \ZipArchive();

        if( ! is_dir($dest) ) return 'Нет папки, куда распаковывать...';

        // открываем архив
        if( true === $zip->open($file_path) ) {

            $zip->extractTo( $dest );

            $zip->close();

            return true;
        }
        else
            return 'Произошла ошибка при распаковке архива';
    }





}