<?php

namespace App\Http\Controllers\Log;

use App\Http\Controllers\Controller;

class LogReaderController extends Controller
{
	public function view()
	{
		return view('LogReader.index')->with([
			'laravelErrors' => $this->getLaravelErrorLogsFromDir('logs'),
			'badRequestErrors' => $this->getLaravelErrorLogsFromDir('logs/BadRequests'),
			'appErrors' => $this->getLaravelErrorLogsFromDir('logs/AppLogs')
		]);
	}

	private function getLaravelErrorLogsFromDir(string $dirPath): string
	{
		$laravelErrors = '';
		
		if (is_dir(storage_path($dirPath))) {
			$laravelErrorLogFiles = scandir(storage_path($dirPath), SCANDIR_SORT_DESCENDING);
		
			if (!empty($laravelErrorLogFiles))
			{
				$counter = 0;
				foreach($laravelErrorLogFiles as $laravelErrorLogFile)
				{
					if (strpos($laravelErrorLogFile, 'log') !== FALSE) {
						if (++$counter > 2)
						{
							break;
						}
		
						$logFilePath = sprintf('%s/%s', storage_path($dirPath), $laravelErrorLogFile);
						$laravelErrors .= $this->lastLines($logFilePath);
					}
				}
			}
		}

		return $laravelErrors;
	}

	private function lastLines(string $file): string 
	{
		$fileContent = '';

		if (file_exists($file))
		{
			$fileSize = filesize($file);

			if ($fileSize) 
			{
				$myfile = fopen($file, 'r+');
				$position = $fileSize;

				$fileContent =  fread($myfile, $position);
	
				fclose($myfile);
			}
		}

		return $fileContent;
	}
}

?>