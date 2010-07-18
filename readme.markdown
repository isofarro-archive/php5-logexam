PHP5 LogExam
============

A scriptable/programmable query infrastructure to web log files. Initial thoughts is to import interesting records into an sqlite table for further analysis.



Example code
------------

### 1. Starting a new dataset ###

### 1.1. Command line

	$ php ./cli_import 'example-dataset' data/access-log.2 data/access-log.3.gz

#### 1.1.2. Deprecated Command line calls
	$ zcat /etc/apache2/logs/access.logs.1.gz | php cli_import.php 'example-dataset'
	$ cat ./data/access.log.3 | php ./cli_import.php 'example-dataset'


### 1.2. Programmatically

	$logfile = '/etc/apache2/logs/access';

	$logexam = new LogExaminer('example_dataset');
	$logexam->import(
		$logfile,
		array(
			'date_from'  => '2010-07-01T06:00',
			'date_to'    => '2010-07-01T09:00',
			'ignore_ext' => array('css','js','gif','png','jpg','swf')
		)
	);