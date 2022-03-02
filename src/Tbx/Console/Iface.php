<?php
namespace Tbx\Console;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @author Michael Mifsud <info@tropotek.com>
 * @see http://www.tropotek.com/
 * @license Copyright 2017 Michael Mifsud
 */
abstract class Iface extends \Tk\Console\Console
{

    /**
     * @var array
     */
    protected $vendorPaths = array();



    /**
     * @param null|string $name
     */
    public function __construct($name = null)
    {
        parent::__construct($name);
        $this->locFile = $this->getConfig()->getTempPath().'/'.md5($this->getCwd().$this->getName()).'.lock';
        $this->setVendorPaths($this->getConfig()->get('vendor.paths'));
    }


    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|null|void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        chdir($this->getCwd());
        $this->setInput($input);
        $this->setOutput($output);
    }

    /**
     * @return array
     */
    public function getVendorPaths()
    {
        return $this->vendorPaths;
    }

    /**
     * @param array $vendorPaths
     * @return $this
     */
    public function setVendorPaths($vendorPaths)
    {
        $this->vendorPaths = $vendorPaths;
        return $this;
    }

    /**
     * return the current working directory
     * @return array|false|string
     */
    public function getCwd()
    {
        //return getcwd();
        return getenv('PWD');   // Seems more reliable
    }
}
