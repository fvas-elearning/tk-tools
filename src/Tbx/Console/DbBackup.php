<?php
namespace Tbx\Console;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

/**
 * @author Michael Mifsud <info@tropotek.com>
 * @see http://www.tropotek.com/
 * @license Copyright 2017 Michael Mifsud
 */
class DbBackup extends Iface
{

    /**
     *
     */
    protected function configure()
    {
        $timestamp = date(\Tk\Date::FORMAT_ISO_DATE);
        $this->setName('dbBackup')
            ->setAliases(array('db'))
            ->addOption('user', 'U', InputOption::VALUE_OPTIONAL, 'The database username.', 'dev')
            ->addOption('pass', 'P', InputOption::VALUE_OPTIONAL, 'The database password.', 'dev007')
            ->addOption('host', 'H', InputOption::VALUE_OPTIONAL, 'The database password.', 'localhost')
            ->addOption('type', 'M', InputOption::VALUE_OPTIONAL, 'The database type.', 'mysql')
            ->addOption('name', 'N', InputOption::VALUE_OPTIONAL, 'The database name to dump, if none then all available databases are dumped', '')
            ->addOption('path', 'p', InputOption::VALUE_OPTIONAL, 'The path to save the archive.', $this->getCwd())
            ->addOption('backupName', 'B', InputOption::VALUE_OPTIONAL, 'the name of the archive', 'dbBackup-' . $timestamp)
            //->addOption('user', 'U', InputOption::VALUE_OPTIONAL, 'The database username.', 'dev')
            ->setDescription('Backup all tables in a DB');
    }


    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|null|void
     * @throws \Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        parent::execute($input, $output);
        $options = $input->getOptions();

        $backupName = $options['backupName'];
        $tempPath = sys_get_temp_dir().'/tk-dbBackup-'.getmyuid();
        if ($input->getOption('name'))
            $tempPath = sys_get_temp_dir().'/'.$input->getOption('name').'-'.getmyuid();

        $backupDir = $tempPath . '/' . $backupName;
        $archivePath = $tempPath . '/' . $backupName . '.tgz';

        if (!is_dir($backupDir))
            mkdir($backupDir, 0777, true);

        $exclude = array('Database', 'information_schema', 'performance_schema', 'phpmyadmin', 'mysql', 'dbispconfig', 'roundcube');
        $databaseList = array($input->getOption('name'));
        if (!$input->getOption('name')) {
            $dsn = 'mysql:host='.$options['host'];
            $db = new \PDO($dsn, $options['user'], $options['pass'], array());
            $dbs = $db->query('SHOW DATABASES');
            $databaseList = $dbs->fetchAll(\PDO::FETCH_COLUMN, 0);
        }

        foreach ($databaseList as $dbName) {
            if (in_array($dbName, $exclude)) continue;
            $this->writeStrong($dbName, OutputInterface::VERBOSITY_VERBOSE);
            $options['name'] = $dbName;
            $db = \Tk\Db\Pdo::create($options);
            \Tk\Util\SqlBackup::create($db)->save($backupDir.'/'.$dbName.'.sql');
        }

        $cmd = sprintf('cd %s && tar zcf %s %s ', $tempPath, basename($archivePath), basename($backupDir));
        $this->writeComment($cmd,OutputInterface::VERBOSITY_VERBOSE);
        system($cmd);

        $cmd = sprintf('mv %s %s ', $archivePath, $options['path']);
        $this->writeComment($cmd,OutputInterface::VERBOSITY_VERBOSE);
        system($cmd);

        $this->write($options['path'].'/'.basename($archivePath));
        \Tk\File::rmdir($tempPath);
    }

}
