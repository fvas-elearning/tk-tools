# Tropotek Toolbox

__Project:__ Tropotek Toolbox  
__Published:__ 01 Jan 2014  
__Web:__ <https://github.com/fvas-elearning/tk-tools>  
__Authors:__ Michael Mifsud <http://www.tropotek.com/>  


This projects contains a number of tools that are handy when developing projects with 
the tk-libs and using Tropotek`s git tag and release system.


## Contents

- [Requirements](#requirements)
- [Installation](#installation)
- [Commands](#commands)


Requirements
============

 - PHP7.1+ (CLI)





Installation
============

 1. Download the source files to somewhere accessible on your server .
 2. Run `composer.phar install`
 3. cd into the bin directory and run `sudo ./install-cmd` to install for all users.  
    Optionally ignore the sudo and the commands will be installed into the user bin directory.



Commands
========

 - **install-cmd** The command to install all scripts to your server
 - **tkCommit** A recursive `svn commit` command that searches for external packages within a project.
 - **tkDbBackupMysql.sh** Script to backup all databases for a user as individual tarballs.
 - **tkLdapFind** Use for quering LDAP services, probably needs a little work but good for basic queries
 - **tklog** Tail a log file in the users path: /home/{username}/log/error.log
 - **tkMd5** Generate an md5 hash of a string
 - **tkStatus** A recursive git status command
 - **tkStrreplace** A simple string replace command.
 - **tkTag** Used to tag a repository, updates composer.json and changelog.md files.
 - **tkTagProject** Recursively tags a Project's repository and its dependant packages, updates composer.json and changelog.md files.
 - **tkTagShow** Show the current tag (version) of the projects and vendow libs (tk libs only)
 - **tkToUtf8** Update files to the UTF-8 encoding.
 - **tkVidHtml5Cnv** Convert video file to a number of formats for web viewing using HTML5 standards.
 - **tkUpdate** A recursive `svn update` command that searches for external packages within a project.
 - **www_fix.sh** changes the permissions of a directory and files recursivly for a public_html/apache folder
 




