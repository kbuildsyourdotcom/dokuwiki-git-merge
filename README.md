door43gitmerge Plugin for DokuWiki

This tool provides a way to merge newly submitted translations from tS.

All documentation for this plugin can be found at
https://github.com/Door43/dokuwiki-git-merge

If you install this plugin manually, make sure it is installed in
lib/plugins/door43gitmerge/ - if the folder is called different it
will not work!

Please refer to http://www.dokuwiki.org/plugins for additional info
on how to install plugins in DokuWiki.

For this plugin to properly function, you must add the following code
to conf/local.php, replacing `PATH_TO_REPO` with the actual path to
the Git repositories:

    $conf['plugin']['door43gitmerge']['repo_path'] = 'PATH_TO_REPO';