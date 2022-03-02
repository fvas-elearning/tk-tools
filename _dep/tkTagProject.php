#! /usr/bin/php
<?php
/*
 * Tropotek Web Development Tools.
 *
 * @author Michael Mifsud <info@tropotek.com>
 * @link http://www.tropotek.com/
 * @license Copyright 2005 Michael Mifsud
 */
//include(dirname(dirname(__FILE__)) . '/vendor/autoload.php');
include dirname(__FILE__) . '/prepend.php';

$argv = $_SERVER['argv'];
$argc = $_SERVER['argc'];

if ($argc < 1 || $argc > 6) {
    echo $help;
    exit;
}

// define global vars
define('MAX_VER', 99999999999);
$cwd = getcwd();
$repo = rtrim(@$argv[1], '/');
$dryRun = false;
$showJson = false;
$staticVer = false;
$forceTag = false;
$tagDeps = true;
$verbose = 0;

$packagePrefixList = array(
    'ttek'          => 'vendor/ttek',
    'ttek-plugin'   => 'plugin',
    'ttek-theme'    => 'theme',
    'ttek-asset'    => 'assets',
    'ttek-plg'      => 'plugin',

    'uom'          => 'vendor/uom',
    'uom-plugin'   => 'plugin',
    'uom-theme'    => 'theme',
    'uom-asset'    => 'assets',
    'uom-plg'      => 'plugin',

    'unimelb'       => 'vendor',
    'unimelb-plg'   => 'plugin',
    'unimelb-theme' => 'theme',
    'ems-plg'       => 'plugin',
    'ems-theme'     => 'theme'
);


$help = "
Usage: " . basename($argv[0]) . " [OPTIONS...]
Tag a release from the repository. Works only on checked out projects.
This command will search the search the project for all packages
in use and tag and release them with new version along with the
parent project.

Available options that this command can receive:

    --static                 If set, then the existing composer.json \'require\'  versions are
                             updated to use specific versions of the libs
                             EG: ~1.0 becomes 1.0.6 for example.
    --force                  Forces a tag version even if there is no change from the previous version
    --notagdeps [-nt]        Tag any dependant libs including the main project.
    --json                   Output package release info as a json object.
    --dryrun                 If set the final svn command is dumped to stdout
    --verbose                [-v|vv|vvv] Increase the verbosity of messages: 1 for normal output, 2 for more verbose output and 3 for debug
    --quiet                  [-q] Turn off all messages, only errors will be displayed
    --help                   Show this help text

Copyright (c) 2002
Report bugs to info@tropotek.com
Tropotek home page: <http://www.tropotek.com/>
";

// Get var values from arguments
foreach ($argv as $param) {

    if (strtolower(substr($param, 0, 7)) == '--quiet') {
        $verbose = 0;
    }
    if (strtolower(substr($param, 0, 2)) == '-q') {
        $verbose = 0;
    }

    if (strtolower(substr($param, 0, 9)) == '--verbose') {
        $verbose = 5;
    }
    if (strtolower(substr($param, 0, 2)) == '-v') {
        $verbose = 1;
    }
    if (strtolower(substr($param, 0, 3)) == '-vv') {
        $verbose = 3;
    }
    if (strtolower(substr($param, 0, 4)) == '-vvv') {
        $verbose = 5;
    }

    if (strtolower(substr($param, 0, 10)) == '--showjson') {
        $showJson = true;
    }
    if (strtolower(substr($param, 0, 10)) == '--tmppath=') {
        $tmp = strtolower(substr($param, 10));
    }
    if (strtolower(substr($param, 0, 10)) == '--forcetag') {
        $forceTag = true;
    }
    if (strtolower(substr($param, 0, 3)) == '-nt') {
        $tagDeps = false;
    }
    if (strtolower(substr($param, 0, 11)) == '--notagdeps') {
        $tagDeps = false;
    }
    if (strtolower(substr($param, 0, 13)) == '--no-tag-deps') {
        $tagDeps = false;
    }
    if (strtolower(substr($param, 0, 8)) == '--static') {
        $staticVer = true;
    }
    if (strtolower(substr($param, 0, 8)) == '--dryrun') {
        $dryRun = true;
    }
    if (strtolower(substr($param, 0, 6)) == '--help') {
        echo $help;
        exit;
    }
}

$dr = '';
if ($dryRun) {
    $dr = '--dryRun';
}
$forceTagStr = '';
if ($forceTag) {
    $forceTagStr = '--force';
}

try {
    $exclude = array('composer.json', 'changelog.md');

    $vcs = new \Tbx\Vcs\Adapter\Git($dryRun);
    $vcs->setVerbose($verbose);

    $currentBranch = $vcs->getCurrentBranch();

    $vcs->commit('Finalising project ' . $currentBranch . ' for tagged release.');

    $vcs->log('Retrieving project composer.json file.');
    // get composer.json file
    $tagJson = $headJson = \Tbx\Util::jsonDecode(file_get_contents('composer.json'));
    if (!$headJson) {
        throw new Exception('Error reading composer.json file in project root.');
    }
    if ($headJson->type != 'project') {
        throw new Exception('Only `project` package types can be released.');
    }
    $packages = array();

    if ($tagDeps) {
        foreach (get_object_vars($headJson->require) as $name => $ver) {
            $regex = implode('|', array_keys($packagePrefixList));
            $regex = '';
            foreach ($packagePrefixList as $k => $v) {
              $regex .= preg_quote($k) . '|';
            }
            if ($regex) rtrim($regex, '|');

            if (!preg_match('/^(' . $regex . ')\//i', $name, $regs)) {
                continue;
            }

            $newVersion = '';
            $depPath = $packagePrefixList[$regs[1]] . '/' . basename($name);

            if (!is_dir($depPath)) {
                $vcs->log('Error: `' . $depPath . '` does not exist, try running `composer update` first.', \Tbx\Vcs\Adapter\Git::LOG_VVV);
                continue;
            }

            $vcs->log('Tagging: ' . $name, \Tbx\Vcs\Adapter\Git::LOG_V);
            $vcs->log('  Tagging Path: ' . $depPath, \Tbx\Vcs\Adapter\Git::LOG_VV);

            $cmd = sprintf('cd %s && tkTag %s %s -v ', $depPath, $dr, $forceTagStr);        // TagRelease

            $vcs->log($cmd, \Tbx\Vcs\Adapter\Git::LOG_CMD);
            $line = exec($cmd, $out, $ret);
            if ($ret) {
                $vcs->log('  Error tagging: ' . $name, \Tbx\Vcs\Adapter\Git::LOG_VVV);
            }
            $out = implode("\n", $out);
            if (preg_match('/  Version: (.+)/i', $out, $regs)) {
                $newVersion = $regs[1];
            }

            $vcs->log($out, \Tbx\Vcs\Adapter\Git::LOG_V);
            //$vcs->log('  Version: ' . $newVersion, \Tbx\Vcs\Adapter\Git::LOG_V);
            if ($staticVer) {
                $tagJson->require->{$name} = $newVersion;
            }
        }
    }

    $curVer = $vcs->getCurrentTag();
    if (!$curVer) {
        $curVer = '0.0.0';
    }
    if ($vcs->isDiff($curVer, $exclude)) {
        // Update composer file
        $tagJson->{'minimum-stability'} = 'stable';
        if (!$dryRun) {
            file_put_contents('composer.json', \Tbx\Util::jsonEncode($tagJson));
        }
        $vcs->commit();

        $cmd = sprintf('tkTag %s %s -v', $dr, $forceTagStr);        // TagRelease
        // Use exec
        $vcs->log($cmd, \Tbx\Vcs\Adapter\Git::LOG_CMD);
        $line = system($cmd);
        $line = trim($line);
        $newVersion = trim(substr($line, 1));
        $released = trim(substr($line, 0, 1)) == '+' ? true : false;

        // Reset the trunk for dev mode
        $headJson->{'minimum-stability'} = 'dev';
        file_put_contents('composer.json', \Tbx\Util::jsonEncode($headJson));
        $vcs->commit();
    }



    echo "\n";
} catch (Exception $e) {
    echo ('ERROR: ' . $e->getMessage() . "\n");
    exit(-1);
}
exit();

/**
 * sortVersionArray
 *
 * @param $array
 * @return bool
 */
function sortVersionArray(&$array)
{
    return usort($array, function ($a, $b) {
        if ($a == $b) {
            return 0;
        } else if (version_compare($a, $b, '<')) {
            return -1;
        } else {
            return 1;
        }
    });
}
