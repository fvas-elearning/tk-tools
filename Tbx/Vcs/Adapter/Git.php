<?php
/**
 * Created by PhpStorm.
 * User: mifsudm
 * Date: 1/30/14
 * Time: 8:58 AM
 */

namespace Tbx\Vcs\Adapter;

/**
 * Class Git
 * Use this to do operations on an Github repository
 *
 *
 * @package Tbx\Vcs\Adapter
 */
class Git extends Iface
{


    /**
     * Commit the current branch and push to remote repos
     *
     * @param string $message
     * @throws \Exception
     * @return bool
     */
    public function commit($message = '')
    {
        $this->output = '';
        $ret = null;
        if ($message) {
            $message = '~Auto: ' . $message;
        } else {
            $message = '~Auto: Commit';
        }
        $cmd = sprintf('git commit -am %s ', escapeshellarg($message));
        $this->log($this->getCmdPrepend().$cmd, self::LOG_CMD);
        if (!$this->isDryRun()) {
            exec($cmd, $this->output, $ret);
        }
        $this->log($this->output, self::LOG_VVV);
        if ($ret) {
            return false;
            //throw new \Exception('Cannot commit branch');
        }

        $cmd = sprintf('git push');
        $this->log($this->getCmdPrepend().$cmd, self::LOG_CMD);
        if (!$this->isDryRun()) {
            exec($cmd, $this->output, $ret);
        }
        $this->log($this->output, self::LOG_VVV);
        if ($ret) {
            return false;
            //throw new \Exception('Cannot push branch');
        }
        return $this;
    }

    /**
     * Commit the current branch and push to remote repos
     *
     * @throws \Exception
     * @return bool
     */
    public function update()
    {
        $cmd = sprintf('git pull ');
        $this->log($cmd, self::LOG_CMD);
        exec($cmd, $this->output, $ret);
        if ($ret) {
            return false;
        }
        return true;
    }


    /**
     * Commit the current branch and push to remote repos
     *
     * @param string $branch
     * @throws \Exception
     * @return bool
     */
    public function checkout($branch = 'master')
    {
        $cmd = sprintf('git checkout %s', escapeshellarg($branch));
        $this->log($cmd, self::LOG_CMD);
        exec($cmd, $this->output, $ret);
        if ($ret) {
            return false;
        }
        return true;
    }


    /**
     * Get the repository package base URI
     *
     * @return string
     */
    public function getUri()
    {
        if (!$this->uri) {
            $this->output = '';
            $cmd = 'git remote -v';
            $this->log($cmd, self::LOG_CMD);
            exec($cmd, $this->output);
            if (preg_match('/^origin\s+(\S+)\s+\((fetch|push)\)/', trim($this->output[0]), $regs)) {
                $this->uri = $regs[1];
            }
        }
        return $this->uri;
    }


    /**
     * Get an array of current tagged versions.
     *
     * @return array
     */
    public function getTagList()
    {
        if (!$this->tagList) {
            $this->output = '';
            $this->tagList = array();

            $cmd = 'git tag ';
            $this->log($cmd, self::LOG_CMD);
            exec($cmd, $this->output);

            foreach($this->output as $line) {
                $line = trim($line);
                if (!$line) continue;
                if (preg_match('/^([0-9\.]+)/i', $line, $regs)) {
                    $this->tagList[$line] = $line;
                }
            }
            $this->sortVersionArray($this->tagList);
        }
        return $this->tagList;
    }


    /**
     * return a list of changed files with out the excluded files.
     *
     * @param string $tagName
     * @param array  $excludeFiles
     * @return array
     */
    public function diff($tagName, $excludeFiles = array())
    {
        $this->output = '';
        $tagName = trim($tagName, '/');
        $cmd = 'git diff --name-status '.escapeshellarg($tagName).' HEAD';
        $this->log($cmd, self::LOG_CMD);
        exec($cmd, $this->output);
        $this->log($this->output, self::LOG_DEBUG);

        $changed = array();
        foreach($this->output as $line) {
            if (!preg_match('/^[a-z]\s+(\S+)/i', $line, $regs)) {
                continue;
            }
            if (in_array(trim($regs[1]), $excludeFiles)) {
                continue;
            }
            $changed[] = trim($regs[1]);
        }
        $this->log($changed, self::LOG_VVV);
        return $changed;
    }


    /**
     * Get an array of changes to the tag since the last copy command was executed.
     *
     * @param string $version
     * @return array
     */
    public function makeChangelog($version)
    {
        $cmd = sprintf('git log --oneline %s..HEAD', escapeshellarg($version));
        $this->log($cmd, self::LOG_CMD);
        exec($cmd, $this->output, $ret);
        if ($ret) {
            return false;
        }
        $exists = array();
        $logs = array();
        $this->log($this->output, self::LOG_DEBUG);
        foreach ($this->output as $i => $log) {
            $msg = $log;
            if (!preg_match('/^([0-9a-f]{7,10})\s+(.+)/i', $msg, $regs)) {
                continue;
            }
            $msg = trim($regs[2]);
            if (strlen($msg) <= 2 || preg_match('/^~?Auto/', $msg)) {
            // Use the below in next major version. Replace the above (1.2.1)
            //if (strlen($msg) <= 2 || preg_match('/^~Auto:/', $msg)) {
                continue;
            }
            if (!in_array(md5($msg), $exists)) {
                $logs[] = $msg;
                $exists[] = md5($msg);
            }
        }
        return $logs;
    }


    /**
     * Tag a new release, basically copy the release to a tag folder
     * Returns true if a new tag was created, false if not.
     *
     * @param string $version A version string in the format of x.x.x
     * @param string $message Any commit message, if non supplied the version will be used
     * @return boolean
     */
    public function tagRelease($version, $message = '')
    {
        if (!$message) {
            $message = 'Tagging new release: '.$version;
        }

        $json = file_get_contents('composer.json');
        if ($json) {
            $jsonTag = json_decode($json);
            $jsonTag->version = $version;
            $jsonTag->time = date('Y-m-d');
            file_put_contents('composer.json', jsonPrettyPrint(json_encode($jsonTag)));
            $this->commit();
        }

        $logArr =  $this->makeChangelog($this->getCurrentTag());
        $log = '';
        if (is_array($logArr)) {
            $this->changelog = sprintf("Ver %s [%s]:\n-------------------------------\n", $version, date('Y-m-d'));
            foreach ($logArr as $line) {
                if (str_word_count($line) <= 1)
                    continue;
                $this->changelog .= " - " . wordwrap(ucfirst($line), 100, "\n   ") . "\n";
            }
            $log = file_get_contents('changelog.md');
            if ($log && $this->changelog && !preg_match('/Ver\s+'.preg_quote($version).'\s+\[[0-9]{4}\-[0-9]{2}\[0-9]{2}\]/i', $this->changelog)) {
                $logTag = '#CHANGELOG#';
                $changelog = $logTag . "\n\n" . $this->changelog;
                $log = str_replace($logTag, $changelog, $log);
            }
            $this->log($log, self::LOG_DEBUG);
        }

        // Tag trunk
        $cmd = sprintf("git tag -a %s -m %s", $version, escapeshellarg($message) );
        $this->output = $cmd;

        // Copy log
        if ($log && $this->changelog) {
            $this->log('  Updating changelog.md.');
            if (!$this->isDryRun()) {
                file_put_contents('changelog.md', $log);
            }
            $this->commit();
        }
        $this->output = array();
        $this->log($this->getCmdPrepend().$cmd, self::LOG_CMD);
        if (!$this->isDryRun()) {
            exec($cmd, $this->output);
        }
        $this->output = implode("\n", $this->output);

        $this->output = array();
        $pushTag = sprintf("git push --tags");
        $this->log($this->getCmdPrepend().$pushTag, self::LOG_CMD);
        if (!$this->isDryRun()) {
            exec($pushTag, $this->output);
        }
        $this->output = implode("\n", $this->output);
        // Restore trunk composer.json
        if ($json) {
            $this->log('  Updating composer.json');
            if (!$this->isDryRun()) {
                file_put_contents('composer.json', $json);
            }
            $this->commit();
        }

        return $this->output;
    }


    /**
     *
     *
     */
    public function getCurrentBranch()
    {
        $cmd = sprintf('git branch');
        $this->log($cmd, self::LOG_CMD);
        exec($cmd, $this->output);

        foreach($this->output as $line) {
            if (preg_match('/^\* (b[0-9]+\.[0-9]+\.[0-9]+)/', $line, $regs)) {
                return $regs[1];
            }
        }
        return 'master';
    }


}