<?php
namespace Study\EmailValidator\Service;

use Shuchkin\SimpleXLSXGen;
ini_set('max_execution_time', 300);
set_time_limit(300);
ini_set('memory_limit', '512M');
error_reporting(E_ALL);

class DDXParser
{
    private $uploadDir;
    private $pathToXLS;
    private $baseDir;
    private $url;
    private $line_data;
    private $track_index;
    private $uploadDirName;

    public function __construct()
    {
        $this->uploadDir = dirname( dirname( __DIR__ ) ) . '/zvonko_upload';
        $this->pathToXLS = dirname( dirname( __DIR__ ) ) . '/storage';
        $this->baseDir = dirname( dirname( __DIR__ ) );
        $this->line_data = [];
        $this->track_index = 0;
        $this->url = "https://ddx.momu.kz";
        $this->uploadDirName = "zvonko_upload";
    }

    public function getFoldersList_new() {
        $filename = $this->pathToXLS.'/folders.csv';

        $folders = scandir($this->uploadDir, SCANDIR_SORT_NONE);
        
        $folders = array_filter($folders, function ($el) {
            if (in_array($el, ['.', '..', 'test']) || !is_dir($this->uploadDir.'/'.$el)) {
                return false;
            }

            $fullPath = $this->uploadDir . '/' . $el;

            $subFolders = scandir($fullPath, SCANDIR_SORT_NONE);
            $subDirs = array_filter($subFolders, function ($sub) use ($fullPath) {
                return !in_array($sub, ['.', '..']) && is_dir($fullPath . '/' . $sub);
            });

            if (empty($subDirs)) {
                return false;
            }

            $subItem = reset($subDirs);
            $releasePath = $fullPath . '/' . $subItem;

            $resourcesPath = $releasePath . '/resources';
            if (!is_dir($resourcesPath)) {
                return false; 
            }

            $filesInRes = scandir($resourcesPath, SCANDIR_SORT_NONE);
            if (count($filesInRes) <= 2) {
                return false;
            }

            $releaseFiles = scandir($releasePath, SCANDIR_SORT_NONE);
            $hasXml = false;
            foreach ($releaseFiles as $file) {
                if ($file === '.' || $file === '..') continue;
                $filePath = $releasePath . '/' . $file;
                if (is_file($filePath) && strtolower(pathinfo($filePath, PATHINFO_EXTENSION)) === 'xml') {
                    $hasXml = true;
                    break;
                }
            }

            return $hasXml;
        });

        $folders = array_values($folders);

        $fp = fopen($filename, 'w');
        fputcsv($fp, $folders);
        fclose($fp);

        return count($folders);
    }
     
    public function getFoldersList() {
        Logger::stepStart('🚀 --- Начало задачи! ---');
        Logger::stepStart('Получение списка папок. Start');

        $filename = $this->pathToXLS.'/folders.csv';

        $folders = scandir($this->uploadDir, SCANDIR_SORT_NONE);
        $folders =  array_filter($folders, function ($el){
            return  !in_array($el, ['.', '..', 'test']) && is_dir($this->uploadDir.'/'.$el);;
        });

        $fp = fopen($filename, 'w');
        fputcsv($fp, $folders);
        fclose($fp);

        $count = count($folders);
        Logger::stepEnd('Получение списка папок. End', "найдено папок: {$count}");

        return $count;
    }

   
   public function ajaxFilter()
   {
        Logger::stepStart('Фильтрация (однопроходная, ajaxFilter)');

        $rawFolders = $this->readFoldersCsv($this->pathToXLS . '/folders.csv');
        if ($rawFolders === null) {
            Logger::stepFail('Фильтрация (однопроходная, ajaxFilter)', 'Файл folders.csv не найден');
            return ['status' => 'error', 'message' => 'Файл со списком не найден!'];
        }

        $this->appendUpcIndexChunk($rawFolders, true);

        $result = $this->ajaxFilterFinalize();
        Logger::stepEnd('Фильтрация (однопроходная, ajaxFilter)', 'итого папок: ' . ($result['total'] ?? 'n/a'));

        return $result;
    }

    public function ajaxFilterStep($offset, $len): array
    {
        $stepName = "2. Фильтрация папок (offset={$offset}, len={$len})";
        Logger::stepStart($stepName);

        $rawFolders = $this->readFoldersCsv($this->pathToXLS . '/folders.csv');
        if ($rawFolders === null) {
            Logger::stepFail($stepName, 'Файл folders.csv не найден');
            return ['status' => 'error', 'message' => 'Файл со списком не найден!'];
        }

        $total = count($rawFolders);
        $offset = max(0, (int)$offset);
        $len = (int)$len > 0 ? (int)$len : $total;
        $chunk = array_slice($rawFolders, $offset, $len);

        $this->appendUpcIndexChunk($chunk, $offset === 0);

        $nextOffset = $offset + count($chunk);

        Logger::stepEnd($stepName, "обработано: {$nextOffset} из {$total}");

        return [
            'status' => 'ok',
            'total' => $total,
            'next_offset' => $nextOffset,
            'done' => $nextOffset >= $total,
        ];
    }

    public function ajaxFilterFinalize(): array
    {
        Logger::stepStart('2. Финализация фильтрации (ajaxFilterFinalize)');

        $indexFilename = $this->pathToXLS . '/upc_index.csv';
        $cleanFilename = $this->pathToXLS . '/folders_clean.csv';

        if (!file_exists($indexFilename)) {
            Logger::stepFail('2. Финализация фильтрации (ajaxFilterFinalize)', 'Индексный файл upc_index.csv не найден');
            return ['status' => 'error', 'message' => 'Индексный файл не найден! Сначала выполните сканирование (ajaxFilterStep).'];
        }

        $latestFolders = [];

        if (($fp = fopen($indexFilename, 'r')) !== false) {
            while (($row = fgetcsv($fp, 0, ',')) !== false) {
                if (count($row) < 3) continue;
                [$upc, $timestamp, $folder] = $row;
                $timestamp = (int)$timestamp;

                if (!isset($latestFolders[$upc]) || $timestamp > $latestFolders[$upc]['timestamp']) {
                    $latestFolders[$upc] = ['folder' => $folder, 'timestamp' => $timestamp];
                }
            }
            fclose($fp);
        }

        $cleanFolders = array_column($latestFolders, 'folder');

        $fp = fopen($cleanFilename, 'w');
        fputcsv($fp, $cleanFolders);
        fclose($fp);

        $count = count($cleanFolders);
        Logger::stepEnd('2. Финализация фильтрации', "актуальных релизов: {$count}");

        return [
            'status' => 'ok',
            'total' => $count
        ];
    }

    private function readFoldersCsv(string $filename): ?array
    {
        if (!file_exists($filename)) {
            return null;
        }

        $data = file($filename, FILE_SKIP_EMPTY_LINES);
        return !empty($data) ? str_getcsv($data[0]) : [];
    }

    private function appendUpcIndexChunk(array $folders, bool $truncate): void
    {
        $indexFilename = $this->pathToXLS . '/upc_index.csv';
        $fp = fopen($indexFilename, $truncate ? 'w' : 'a');
        if ($fp === false) {
            return;
        }

        foreach ($folders as $folder) {
            $folder = trim($folder);
            if ($folder === '') continue;

            $info = $this->extractUpcTimestampFromFolder($folder);
            if ($info === null) continue;

            fputcsv($fp, [$info['upc'], $info['timestamp'], $folder]);
        }

        fclose($fp);
    }

    private function extractUpcTimestampFromFolder(string $folder): ?array
    {
        $fullPath = $this->uploadDir . '/' . $folder;

        $subFolders = @scandir($fullPath, SCANDIR_SORT_NONE);
        if ($subFolders === false) {
            return null;
        }

        foreach ($subFolders as $sub) {
            if ($sub === '.' || $sub === '..') continue;

            $parts = explode('_', $sub, 2);
            if (count($parts) === 2) {
                return ['upc' => $parts[0], 'timestamp' => (int)$parts[1]];
            }
        }

        return null;
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

     public function parse($offset, $len)
    {
        $batchStep = "3. Парсинг пачки релизов (offset={$offset}, len={$len})";
        Logger::stepStart($batchStep);

        if (ob_get_level()) ob_clean();
        header('Content-Type: application/json; charset=utf-8');
 
        $filename = $this->pathToXLS . '/folders_clean.csv';
        if (!file_exists($filename)) {
            Logger::stepFail($batchStep, 'Нет файла folders_clean.csv, фильтрация не выполнена');
            return ['errors' => ['Нет отфильтрованного файла со списком! Сначала выполните фильтрацию.']];
        }

        Logger::stepStart('3. Чтение folders_clean.csv');
        if (($open = fopen($filename, "r")) !== false) {
            while (($data = fgetcsv($open, null, ",")) !== false) {
                $array[] = $data;
            }
            fclose($open);
        }
        Logger::stepEnd('3. Чтение folders_clean.csv');

        try {

        
       
            $rawFolders = $array[0] ?? [];

        
            $offset = (int)$offset ?? 0;
            $len = (int)$len > 0 ? (int)$len : count($rawFolders);
            $folders = array_slice($rawFolders, $offset, $len);
                    
            $parseErrors = [];

            $xmlStep = '3. Парсинг XML-файлов пачки (' . count($folders) . ' папок)';
            Logger::stepStart($xmlStep);

            foreach ($folders as $folder)
            {
                $file_info = $this->findXMLFileInFolder($folder);
                $xml_file_name = $file_info[0];

                

                $ext = pathinfo($xml_file_name, PATHINFO_EXTENSION);
                $dom = new \DOMDocument();
                libxml_use_internal_errors(true);
                if(file_exists($xml_file_name) && strtolower($ext) == "xml") {
                    if ($dom->load($xml_file_name)) {
                        $xml = simplexml_load_file($xml_file_name);
                        $this->collectMetaDataForOneDDX($xml, $this->url.'/'.$this->uploadDirName .'/'.$file_info[1]);
                    } else {
                        $libxmlErrors = libxml_get_errors();
                        $errorDetails = [];
                        foreach ($libxmlErrors as $err) {
                            $errorDetails[] = trim($err->message) . " (Строка: {$err->line})";
                        }
                        libxml_clear_errors();
                    }
                }




            }

            Logger::stepEnd($xmlStep, 'строк метаданных собрано: ' . count($this->line_data));

            $columnNames = ['track_id', 'upc', 'isrc', 'track_number', 'track_name','genre_name', 'album_id','album_name', 'artist_name', 'composer','lyricist', 'explicit', 'duration', 'label', 'author_right', 'author_right_id', 'related_right', 'related_right_id','countries', 'release_date', 'path_to_resource'];
            $isFirstBatch = ($offset == 0);

            Logger::stepStart('Запись metadata.csv');
            $this->arrayToCsv($this->line_data, $isFirstBatch ? $columnNames : null, 'metadata.csv', $isFirstBatch ? 'w' : 'a');
            Logger::stepEnd('Запись metadata.csv');

            $nextOffset = $offset + count($folders);

            $allCleanFolders = [];
            if (($fp = fopen($filename, "r")) !== false) {
                while (($data = fgetcsv($fp, 0, ",")) !== false) {
                    $allCleanFolders = $data;
                    break;
                }
                fclose($fp);
            }
            $totalCleanCount = count($allCleanFolders);
            $nextOffset = $offset + count($folders);

            $excelFiles = [];
            if ($nextOffset >= $totalCleanCount && $totalCleanCount > 0) {
                $excelFiles = $this->createExcelFile();
                Logger::stepStart("Генерация Excel файла " . implode(', ', $excelFiles));
            }

            $firstExcelUrl = !empty($excelFiles[0]) ? $this->url . '/' . $excelFiles[0] : '';
            $result = [
                'url' => $firstExcelUrl,
                'excel_files' => $excelFiles,
                'url_csv' => $this->url . '/storage/metadata.csv',
                'offset' => $offset,
                'len' => $len + $offset,
                'next_offset' => $nextOffset
            ];

            Logger::stepEnd($batchStep, 'обработано папок: ' . count($folders));

            return $result;
          
    
        } catch (\Throwable $e) {
            Logger::stepFail($batchStep, $e->getMessage());
            Logger::logException($e, 'DDXParser::parse');

            http_response_code(500);
            echo json_encode([
                'status' => 'error',
                'message' => 'PHP Error: ' . $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            exit;
        }
    }

    public function createExcelFile()
    {
        $csvPath = $this->pathToXLS . '/metadata.csv';
        if (!file_exists($csvPath)) {
            Logger::stepFail("Генерация XLSX", "Файл metadata.csv не найден");
            return [];
        }

        Logger::stepStart("Чтение metadata.csv для генерации XLSX");
        
        $csvRows = [];
        if (($handle = fopen($csvPath, "r")) !== false) {
            while (($row = fgetcsv($handle, 0, ',')) !== false) {
                $csvRows[] = $row;
            }
            fclose($handle);
        }

        if (empty($csvRows)) {
            Logger::stepFail("Генерация XLSX", "Файл metadata.csv пуст");
            return [];
        }

        $columnNames = ['track_id', 'upc', 'isrc', 'track_number', 'track_name','genre_name', 'album_id','album_name', 'artist_name', 'composer','lyricist', 'explicit', 'duration', 'label', 'author_right', 'author_right_id', 'related_right', 'related_right_id','countries', 'release_date', 'path_to_resource'];
        
        if ($csvRows[0] === $columnNames) {
            array_shift($csvRows);
        }

        $totalDataRows = count($csvRows);
        Logger::stepEnd("Чтение metadata.csv", "Всего строк данных: {$totalDataRows}");

        if ($totalDataRows === 0) {
            $excelFileName = 'storage/metadata_part_1.xlsx';
            $this->arrayToXLSX([$columnNames], $excelFileName);
            return [$excelFileName];
        }


        $maxRowsPerExcel = 500000;
        $chunks = array_chunk($csvRows, $maxRowsPerExcel);
        $generatedFiles = [];

        foreach ($chunks as $index => $chunk) {
            $excelPart = $index + 1;
            $excelFileName = 'storage/metadata_part_' . $excelPart . '.xlsx';

            $xlsxStep = "Генерация XLSX ({$excelFileName}) — пачка {$excelPart} из " . count($chunks);
            Logger::stepStart($xlsxStep);

            array_unshift($chunk, $columnNames);
            $this->arrayToXLSX($chunk, $excelFileName);

            $generatedFiles[] = $excelFileName;
            Logger::stepEnd($xlsxStep);
        }

        return $generatedFiles;
    }

    public function parse_old($offset, $len)
    {
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

            $ext = pathinfo($xml_file_name, PATHINFO_EXTENSION);
            $dom = new \DOMDocument();
            libxml_use_internal_errors(true);
            if(file_exists($xml_file_name) && strtolower($ext) == "xml") {
                if ($dom->load($xml_file_name)) {
                    $xml = simplexml_load_file($xml_file_name);
                    $this->collectMetaDataForOneDDX($xml, $this->url.'/'.$this->uploadDirName .'/'.$file_info[1]);
                } else {
                    print $xml_file_name ."<br>";
                }

            }
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


    public function parse_new($offset, $len)
    {
        $filename = $this->pathToXLS.'/folders.csv';
        if(!file_exists($filename))
            return ['errors' => ['Нет файла со списком! Сначала сформируйте файл!']];

        if (($open = fopen($filename, "r")) !== false) {
            while (($data = fgetcsv($open, null, ",")) !== false) {
                $array[] = $data;
            }
            fclose($open);
        }
        
        $rawFolders = $array[0] ?? [];


        $latestFolders = [];
        foreach ($rawFolders as $folder) {
            $folder = trim($folder);
            if (empty($folder)) continue;

        
            $fullPath = $this->uploadDir . '/' . $folder;
            $upc = null;
            $timestamp = 0;

            if (is_dir($fullPath)) {
                $subFolders = scandir($fullPath, SCANDIR_SORT_NONE);
                foreach ($subFolders as $sub) {
                    if ($sub === '.' || $sub === '..') continue;
                    
                
                    if (preg_match('/^(\d+)_(\d{10,})$/', $sub, $matches)) {
                        $upc = $matches[1];
                        $timestamp = (int)$matches[2];
                        break;
                    }
                }
            }
            
            if (!$upc) {
                preg_match('/(\d+)_(\d{10,})/', $folder, $matches);
                if (!empty($matches)) {
                    $upc = $matches[1];
                    $timestamp = (int)$matches[2];
                } else {
                    $upc = $folder;
                    $timestamp = 0;
                }
            }
            
            if (!isset($latestFolders[$upc]) || $timestamp > $latestFolders[$upc]['timestamp']) {
                $latestFolders[$upc] = [
                    'folder' => $folder,
                    'timestamp' => $timestamp
                ];
            }
        }
 
        $cleanFolders = array_values(array_column($latestFolders, 'folder'));

        $offset = (int)$offset ?? 0;
        $len = (int)$len > 0 ? (int)$len : count($cleanFolders);
        $folders = array_slice($cleanFolders, $offset, $len);
                
    
        foreach ($folders as $folder)
        {
            $file_info = $this->findXMLFileInFolder($folder);
            $xml_file_name = $file_info[0];

            $ext = pathinfo($xml_file_name, PATHINFO_EXTENSION);
            $dom = new \DOMDocument();
            libxml_use_internal_errors(true);
            if(file_exists($xml_file_name) && strtolower($ext) == "xml") {
                if ($dom->load($xml_file_name)) {
                    $xml = simplexml_load_file($xml_file_name);
                    $this->collectMetaDataForOneDDX($xml, $this->url.'/'.$this->uploadDirName .'/'.$file_info[1]);
                } else {
                    print $xml_file_name ."<br>";
                }
            }
        }

        $columnNames = ['track_id', 'upc', 'isrc', 'track_number', 'track_name','genre_name', 'album_id','album_name', 'artist_name', 'composer','lyricist', 'explicit', 'duration', 'label', 'author_right', 'author_right_id', 'related_right', 'related_right_id','countries', 'release_date', 'path_to_resource'];

            $isFirstBatch = ($offset == 0);
            $this->arrayToCsv($this->line_data, $isFirstBatch ? $columnNames : null, 'metadata.csv', $isFirstBatch ? 'w' : 'a');

            $csvPath = $this->pathToXLS . '/metadata.csv';
            $totalRows = 0;
            if (file_exists($csvPath)) {
                $lineCount = 0;
                $handle = fopen($csvPath, "r");
                while (($line = fgets($handle)) !== false) {
                    $lineCount++;
                }
                fclose($handle);
                $totalRows = max(0, $lineCount - 1);
            }

            $maxRowsPerExcel = 500000;
            $excelPart = floor($totalRows / $maxRowsPerExcel) + 1;
            $excelFileName = 'storage/metadata_part_' . $excelPart . '.xlsx';

            $xlsxData = $this->line_data;
            array_unshift($xlsxData, $columnNames);
            $this->arrayToXLSX($xlsxData, $excelFileName);

            $result = [
                'url' => $this->url . '/' . $excelFileName,
                'url_csv' => $this->url . '/storage/metadata.csv',
                'offset' => $offset,
                'len' => $len + $offset
            ];

            return $result;
    }

   
    private function getResultFileName(int $offset, int $len)
    {
        return  'storage/metadata_'.$offset.'_'.$len+$offset.'.xlsx';
    }

    private function findXMLFileInFolder(string $mainFolder_name)
    {
        $folder = $this->uploadDir.'/'.$mainFolder_name;
        if (!is_dir($folder)) {
            return [null, null];
        }
        $dir = scandir($folder);
        $dir =  array_filter($dir, function ($el) use ($folder){
            return !in_array($el, ['.', '..']) && is_dir($folder.'/'.$el);
        });
        $item = array_pop($dir);
        $parentFolder = $mainFolder_name.'/'.$item;
        $folder = $folder.'/'.$item;

        $dir = scandir($folder);
        $dir =  array_filter($dir, function ($el) use ($folder){
            return !in_array($el, ['.', '..']) && is_file($folder.'/'.$el) ;
        });
        $xml =  $folder.'/'.array_pop($dir);

        return [$xml, $parentFolder ];
    }
    public static function simpleXmlToArray(\SimpleXMLElement $xml)
    {
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
            $album_name  = (string) 'Single';
            $release_date  = (string)$xml2->ReleaseList->Release->ReleaseDetailsByTerritory->OriginalReleaseDate;
        }

        $resource_list  = $xml2->ResourceList->SoundRecording;
        $i = $this->track_index;
        $k = 0;
        foreach($resource_list as $resource)
        {
            
            if (!isset($resource->SoundRecordingDetailsByTerritory->TechnicalSoundRecordingDetails->File)) {
                continue; 
            }
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

            $artists  = self::simpleXmlToArray($resource->SoundRecordingDetailsByTerritory)['DisplayArtist'];
            $artist = $this->getArtists($artists);
            $this->line_data[$i]['artist_name'] = $artist['artist_name'];
            $this->line_data[$i]['Composer'] = $artist['composer'];
            $this->line_data[$i]['Lyricist'] = $artist['lyricist'];

            $this->line_data[$i]['explicit'] = (string)$resource->SoundRecordingDetailsByTerritory->ParentalWarningType;
            $this->line_data[$i]['duration2'] = $this->formatTime((string)$resource->Duration);
            $this->line_data[$i]['label'] = (string)$resource->SoundRecordingDetailsByTerritory->LabelName;

            $this->line_data[$i]['author_right']  = $rights[$k]['RightsAdministrator'];
            $this->line_data[$i]['author_right_id']  = ' ';
            $this->line_data[$i]['related_right']  = $rights[$k]['RightsController'];
            $this->line_data[$i]['related_right_id']  = ' ';

            $this->line_data[$i]['countries'] = (string)$resource->SoundRecordingDetailsByTerritory->TerritoryCode;
            $this->line_data[$i]['release_date'] = $release_date;
            $this->line_data[$i]['path'] = $pathToResource;

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
        $xlsx =  SimpleXLSXGen::fromArray( $files_meta );
        $xlsx->saveAs($this->baseDir.'/'.$fileName);
    }
    private  function arrayToCsv($array,   $columnTitles, $filename = 'metadata.csv', $mode = 'w') {
        $filename = $this->pathToXLS.'/'.$filename;
        $fp = fopen($filename, $mode);

        if(!empty($columnTitles)){
            fputcsv($fp, $columnTitles);
        }

        foreach ($array as $entry) {
            $values = $entry;
            fputcsv($fp, $values);
        }

        fclose($fp);
    }
    private function unzip_file($file_path, $dest ){
        $zip = new \ZipArchive();

        if( ! is_dir($dest) ) return 'Нет папки, куда распаковывать...';

        if( true === $zip->open($file_path) ) {

            $zip->extractTo( $dest );

            $zip->close();

            return true;
        }
        else
            return 'Произошла ошибка при распаковке архива';
    }





}