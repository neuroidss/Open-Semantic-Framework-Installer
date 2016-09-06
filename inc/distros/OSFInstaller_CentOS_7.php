<?php

  include_once('inc/OSFInstaller.php');

  class OSFInstaller_CentOS_7 extends OSFInstaller
  {

    /**
     * Prepare the distribution with the minimum requirements
     */
    public function prepareDistro()
    {
      $this->h1("Installing prerequisites");

      if($this->upgrade_distro) {
        $this->updateDistro();
      }
      
      // If the EPEL repository doesn't exist on the server, then add it
      if($this->exec('yum repolist | grep epel', 'ignore') > 0)
      {
        $this->chdir('/tmp');
        
        $this->wget('http://dl.fedoraproject.org/pub/epel/7/x86_64/e/epel-release-7-5.noarch.rpm');
        
        $this->exec('rpm -ivh epel-release-7-5.noarch.rpm');
        
        $this->rm('epel-release-7-5.noarch.rpm');
      }
      
      // SELinux to permissive
      $this->exec('setenforce permissive');
      $this->sed('SELINUX=enforcing', 'SELINUX=permissive', '/etc/selinux/config');      

      $this->span("Installing required general packages...");
      $this->exec('yum install -y \
                       initscripts wget \
                       curl vim \
                       gcc gawk \
                       openssl openssl-devel');
    }

    /**
     * Update the distribution packages
     */
    public function updateDistro()
    {
      $this->span("Updating the package registry...");
      $this->exec('yum makecache');

      $this->span("Upgrading the server...");
      $this->exec('yum update -y');
    }

    /**
     * Install PHP as required by OSF and OSF-Drupal
     */
    public function installPHP()
    {
      $this->h1("Installing PHP");

      $this->span("Installing PHP...");
      passthru('yum install -y php php-devel php-pear php-cli \
                               php-embedded php-cgi php-mbstring \
                               php-gd php-pdo php-mysql php-odbc \
                               php-mcrypt php-imap');
                               
      $this->span("Configuring PHP...");
      
      $this->sed(';date.timezone =', 'date.timezone = UTC', '/etc/php.ini');
    }

    /**
     * Install Java as required by Tomcat, Solr, Owl and Scones
     */
    public function installJava()
    {
      $this->h1("Installing Java");

      $this->span("Installing Java6/7...");
      $this->exec('yum install -y \
        java \
        java-gcj java-openjdk jdk ecj \
        java-1.6.0-openjdk java-1.7.0-openjdk \
        log4j slf4j wsdl4j tzdata-java \
        jakarta-commons-logging jakarta-taglibs-standard');
    }

    /**
     * Install Apache as required by OSF and OSF-Drupal
     */
    public function installApache()
    {
      $this->h1("Installing Apache");

      $this->span("Installing Apache2...");
      $this->exec('yum install -y httpd httpd-devel httpd-tools mod_ssl');

      $this->span("Enabling mod-rewrite...");
      $this->exec('echo "LoadModule rewrite_module modules/mod_rewrite.so" > /etc/httpd/conf.modules.d/00-rewrite.conf');

      $this->span("Restarting Apache2...");
      $this->exec('systemctl restart httpd.service');

      $this->span("Performing some tests on the new Apache2 instance...");
      $this->span("Checking if the Apache2 instance is up and running...");

      if(strpos(shell_exec('curl -s http://localhost'), 'Apache HTTP Server Test Page powered by CentOS') === FALSE) {
        $this->span("[Error] Apache2 is not currently running...", 'warn');
      }
    }

    /**
     * Install Tomcat as required by Solr, Owl and Scones
     */
    public function installTomcat()
    {
      $this->h1("Installing Tomcat");

      $this->span("Installing Tomcat...");
      $this->exec('yum install -y \
        tomcat \
        tomcat-admin-webapps tomcat-webapps');

      $this->span("Restarting Tomcat...");
      $this->exec('systemctl restart tomcat.service');
    }

    /**
     * Install MySQL as required by OSF-Drupal
     */
    public function installSQL($mode = 'server')
    {
      switch($mode) {

        // MySQL client installation
        case 'client':
          $this->h1("Installing 'MariaDB' client");

          $this->span("Installing MariaDB client...");
          // Need to use passthru because the installer prompts the user
          // with screens requiring input.
          // This command cannot be captured in the log.
          passthru('yum install -y mariadb');
          break;

        // MySQL server installation
        case 'server':
          $this->h1("Installing MariaDB server");

          $this->span("Installing MariaDB server...");
          // Need to use passthru because the installer prompts the user
          // with screens requiring input.
          // This command cannot be captured in the log.
          passthru('yum install -y mariadb-server');
          break;

      }
    }

    /**
    * Install PHPUnit as required by OSF
    */
    public function installPHPUnit()
    {
      $this->h1("Installing PHPUnit");
      
      // Get name, version and paths
      $pkgName = "PHPUnit";
      $installPath = "/usr/bin";
      $tmpPath = "/tmp/osf/phpunit";

      // Download
      $this->span("Downloading...");
      $this->mkdir("{$tmpPath}/");
      $this->wget("https://phar.phpunit.de/phpunit-old.phar", "{$tmpPath}/");

      // Install
      $this->span("Installing...");
      $this->cp("{$tmpPath}/phpunit-old.phar", "{$installPath}/phpunit", FALSE);
      $this->chmod("{$installPath}/phpunit", "+x");

      // Cleanup
      $this->span("Cleaning...");
      $this->rm("{$tmpPath}/", TRUE);
    }

    /**
    * Install ARC2 as required by OSF
    */
    public function installARC2()
    {
      $this->h1("Installing ARC2");

      $this->span("Installing ARC2...");
      
      $this->chdir("{$this->osf_web_services_folder}/{$this->osf_web_services_ns}/framework/arc2/");
      
      $this->wget('https://github.com/semsol/arc2/archive/v2.1.1.zip');
      
      $this->exec('unzip v2.1.1.zip');
      
      $this->chdir("{$this->osf_web_services_folder}/{$this->osf_web_services_ns}/framework/arc2/arc2-2.1.1/");
      
      $this->mv('*', '../');
      
      $this->chdir("{$this->osf_web_services_folder}/{$this->osf_web_services_ns}/framework/arc2/");
      
      $this->rm('arc2-2.1.1', TRUE);
      
      $this->rm('v*.zip*');
      
      $this->chdir($this->currentWorkingDirectory);
    }   

    /**
    * Install OWLAPI as required by OSF
    */
    public function installOWLAPI()
    {
      $this->h1("Installing OWLAPI");
      
      $this->span("Downloading OWLAPI...");
      
      $this->chdir('/var/lib/tomcat/webapps/');
      
      // Remove default Tomcat projects
      $this->rm('examples', TRUE);
      $this->rm('host-manager', TRUE);
      $this->rm('manager', TRUE);
      $this->rm('ROOT', TRUE);
      $this->rm('sample', TRUE);      
      
      $this->wget('http://wiki.opensemanticframework.org/files/OWLAPI.war');
      
      $this->span("Starting Tomcat to install the OWLAPI war installation file...");
      
      $this->exec('systemctl restart tomcat.service');
      
      // wait 20 secs to make sure Tomcat7 had the time to install the OWLAPI webapp
      sleep(20);
      
      $this->span("Configuring PHP for the OWLAPI...");
      
      $this->sed('allow_url_include = Off', 'allow_url_include = On', '/etc/php.ini');

      $this->span("Restart Apache2...");
      $this->exec('systemctl restart httpd.service');
    }

    /**
    * Install OSFvhost as required by OSF
    */
    public function install_OSF_vhost()
    {
      $this->h1("Installing OSF vhost");
      
      $this->span("Configure Apache2 for the OSF Web Services...");
      
      $this->cp('resources/osf-web-services/osf-web-services', '/etc/httpd/conf.d/osf-web-services.conf');

      // Fix the OSF Web Services path in the apache config file
      $this->sed('/usr/share/osf', "{$this->osf_web_services_folder}/{$this->osf_web_services_ns}", '/etc/httpd/conf.d/osf-web-services.conf');
      
      $this->span("Restarting Apache2...");
      
      $this->exec('systemctl restart httpd.service');
      
      $this->span("Configure the osf.ini configuration file...");

      $this->span("Make sure the OSF Web Services are aware of themselves by changing the hosts file...");
      
      if(stripos(file_get_contents('/etc/hosts'), 'OSF-Installer') == FALSE)
      {
        file_put_contents('/etc/hosts', "\n\n# Added by the OSF-Installer to make the OSF Web Services are aware of themselves\n127.0.0.1 ".$this->osf_web_services_domain, FILE_APPEND);
      }
    }

    /**
    * Install Virtuoso as required by OSF
    */
    public function installVirtuoso()
    {
      $this->h1("Installing Virtuoso 7 from .rpm file....");   

      $this->span('Install redhat-lsb dependency...');
      $this->exec('yum install -y redhat-lsb');
      
      $this->wget("https://github.com/structureddynamics/OSF-Installer-Ext/raw/{$this->installer_version}/virtuoso-opensource/virtuoso-opensource-7.2.1.x86_64--centos-7.rpm");
      $this->exec('rpm -ivh virtuoso-opensource-7.2.1.x86_64--centos-7.rpm');
      
      $this->sed('virtuoso-opensource-6.0', 'virtuoso', '/etc/rc.d/init.d/virtuoso-opensource');
      
      $this->mv('/etc/rc.d/init.d/virtuoso-opensource', '/etc/rc.d/init.d/virtuoso');
      
      $this->span("Installing odbc.ini and odbcinst.ini files...");
      
      $this->chdir($this->currentWorkingDirectory);
      
      $this->append("\n\n".file_get_contents('resources/virtuoso/odbc.ini'), '/etc/odbc.ini');
      $this->append("\n\n".file_get_contents('resources/virtuoso/odbcinst.ini'), '/etc/odbcinst.ini');

      $this->exec('systemctl start virtuoso.service');
      
      sleep(20);
      
      if($this->exec('ps -cax | grep virtuoso', 'ignore') > 0)
      {
        $this->span('Virtuoso is not running. Check the logs, something went wrong.', 'error');
      }
      else
      {
        $this->span("Register Virtuoso to automatically start at the system's startup...");
        $this->exec('/sbin/chkconfig --add virtuoso');
        $this->exec('/sbin/chkconfig virtuoso on');

        if(!$this->change_password($this->sparql_password))
        {
          $this->sparql_password = 'dba';
          $this->span("\n\nThe Virtuoso admin password was not changed. Use the default and change it after this installation process...\n", 'warn');
        }        
        
        $this->span("Grant the SPARQL_UPDATE role to the SPARQL user...");

        if(!$this->update_sparql_roles($this->sparql_password))
        {
          $this->span("\n\nCouldn't grant the SPARQL_UPDATE role to the SPARQL user automcatilly. Log into Conductor to add that role to that user otherwise the OSF instance won't be operational...\n", 'error');
        }                
      }
      
      // Configuring Virtuoso to be able to access the files from the DMT tool
      $this->span("Configuring virtuoso.ini...");
      
      $this->sed('DirsAllowed.*= ., /usr/share/virtuoso/vad', 'DirsAllowed = ., /usr/share/virtuoso/vad, /usr/share/datasets-management-tool/data', '/var/lib/virtuoso/db/virtuoso.ini');
      
      $this->span("Restarting Virtuoso...");
      
      $this->exec('systemctl restart virtuoso.service');
      
      sleep(20);
            
      $this->span("You can start Virtuoso using this command: systemctl start virtuoso.service", 'debug');
    }

    /**
    * Setup Virtuoso as required by OSF
    */
    public function setupVirtuoso()
    {
      $this->h1("Setting up Virtuoso");

      $this->span("Create the WSF Network...");
      
      $this->chdir($this->currentWorkingDirectory);
      
      $this->sed("server_address = \"\"", "server_address = \"http://{$this->osf_web_services_domain}\"", 'resources/virtuoso/initialize_osf_web_services_network.php');
      $this->sed("appID = \"administer\"", "appID = \"{$this->application_id}\"", 'resources/virtuoso/initialize_osf_web_services_network.php');
      
      $errors = shell_exec('php resources/virtuoso/initialize_osf_web_services_network.php');
      
      if(!$this->init_osf($this->sparql_password))
      {
        $this->span("\n\nThe OSF Web Services Network couldn't be created. Major Error.\n", 'error');
      }        
      
      $this->span("Commit transactions to Virtuoso...");      

      if(!$this->commit($this->sparql_password))
      {
        $this->span("Couldn't commit triples to the Virtuoso triples store...", 'warn');
      }
    }

    /**
    * Install Solr as required by OSF
    */
    public function installSolr()
    {
      $this->h1("Installing Solr");
      
      $this->span("Preparing installation...");

      $this->mkdir('/tmp/solr-install/');

      $this->chdir('/tmp/solr-install/');
      
      $this->span("Downloading Solr...");
      
      $this->wget('http://archive.apache.org/dist/lucene/solr/3.6.0/apache-solr-3.6.0.tgz');
      
      $this->span("Installing Solr...");

      $this->exec('tar -xzvf apache-solr-3.6.0.tgz');

      $this->mkdir('/usr/share/solr');

      $this->cp('/tmp/solr-install/apache-solr-3.6.0/*', '/usr/share/solr/');
      
      $this->span("Configuring Solr...");
      
      $this->chdir($this->currentWorkingDirectory);

      $this->cp('resources/solr/solr', '/etc/init.d/');

      $this->chmod('/etc/init.d/solr', '755');

      $this->mv('/usr/share/solr/example/', '/usr/share/solr/osf-web-services/');
      
      $this->span("Installing SOLR-2155...");
      
      $this->chdir('/usr/share/solr/dist/');
      
      $this->wget('https://github.com/downloads/dsmiley/SOLR-2155/Solr2155-1.0.5.jar');
      
      $this->chdir($this->currentWorkingDirectory);
      
      $this->cp('resources/solr/solrconfig.xml', '/usr/share/solr/osf-web-services/solr/conf/');

      $this->span("Starting Solr...");

      $this->exec('service solr start');
      
      $this->span('Register Solr to automatically start at the system\'s startup...');
      
      // @TODO Upgrade this to create a systemd services and to do the same procedure we
      //       did for Virtuoso to start Solr at startup.
      $this->append('/etc/init.d/solr start', '/etc/rc.local');      
      $this->chmod('/etc/rc.d/rc.local', '+x');
      
      $this->span("You can start Solr using this command: service solr start", 'notice');
    }    

    /**
    * Setup Solr as required by OSF
    */
    public function setupSolr()
    {
      $this->h1("Setting up Solr");

      $this->span("Install the Solr schema for the OSF Web Services...");
      
      if(!file_exists('/usr/share/solr/osf-web-services/solr/conf/schema.xml'))
      {
        $this->span("Solr is not yet installed. Install Solr using this --install-solr option and then properly configure its schema by hand.");
      }
      else
      {
        $this->cp("{$this->osf_web_services_folder}/{$this->osf_web_services_ns}/framework/solr_schema_v1_3_2.xml", '/usr/share/solr/osf-web-services/solr/conf/schema.xml');
        
        $this->span("Restarting Solr...");
        $this->exec('service solr stop');
        $this->exec('service solr start');
      }
    }
    
    /**
    * Install MySQL as required by OSF
    */
    public function installPhpMyAdmin()
    {
      $this->h1("Installing PhpMyAdmin");

      $this->span("Installing PhpMyAdmin...");
            
      $this->exec('yum install -y phpmyadmin');
      
      $this->exec('systemctl restart httpd.service');
      
      $this->span('In order to be able to log into the user interface, make sure you add your IP address in the conf file: /etc/httpd/conf.d/phpMyAdmin.conf and restarting httpd.', 'warn');
    }       
    
    /**
    * Install Memcached as required by OSF
    */    
    public function installMemcached()
    {
      $this->h1("Installing Memcached");

      $this->span("Installing Memcached...");
      
      $this->exec('yum install install -y memcached php-pecl-memcache');
      
      $this->span("Configuring Memcached...");
      
      $this->sed("OPTIONS=\"\"", "OPTIONS=\"-l 127.0.0.1\"", "/etc/sysconfig/memcached");
      
      $this->span("Restarting Apache2...");
      
      $this->exec('systemctl restart httpd.service');
      
      $this->span("Starting Memcached...");

      $this->exec('systemctl restart memcached.service');
   
      $this->span("Installing Memcached User Interface...");
      
      $this->mkdir('/usr/share/memcached-ui/');
      
      $this->chdir('/usr/share/memcached-ui/');
      
      $this->wget('http://artur.ejsmont.org/blog/misc/uploads/memcache_stats_v0.1.tgz');
      $this->exec('tar -xvf memcache_stats_v0.1.tgz');
      
      $this->chdir('memcache_stats_v01/');

      $this->mv('*', '../');
      
      $this->chdir('/usr/share/memcached-ui/');

      $this->rm('memcache_stats_v01', TRUE);
      $this->rm('*.tgz', TRUE);      
      
      $this->mv('memcache.php', 'index.php');

      $this->sed("define('ADMIN_PASSWORD','pass');", "define('ADMIN_PASSWORD','{$this->keycache_ui_password}');", 'index.php');
      
      $this->span("Configuring Apache2 for the Memcached User Interface...");
      
      $this->chdir($this->currentWorkingDirectory);
      
      $this->cp('resources/memcached/memcached', '/etc/httpd/conf.d/memcached.conf');
      
      $this->span("Restarting Apache2...");
      
      $this->exec('systemctl restart httpd.service');      
    }       
    
    public function install_OSF_Drupal()
    { 
      // Instally the SQL engine used by Drupal
      
      $this->installSQL('server');
      $this->installSQL('client');
      
      $this->exec('systemctl restart mariadb.service');
      
      // Run the MariaDB tool to setup a secure installation
      $this->exec('/usr/bin/mysql_secure_installation');
      
      $this->exec('systemctl restart httpd.service');

      $this->installPhpMyAdmin();      
           
      // Install Drush
      
      // Install composer
      $this->chdir('/tmp/');
      
      $this->exec('curl -sS https://getcomposer.org/installer | php');
      $this->mv('composer.phar', '/usr/bin/composer');
      
      // Install Drush
      $this->exec('composer global require drush/drush:7.1.0');
      
      $this->ln('/root/.composer/vendor/drush/drush/drush', '/usr/bin/drush');        
      
      // Install Drupal            
      $this->h1("Installing Drupal & OSF Drupal");      
      
      $this->chdir($this->currentWorkingDirectory);
      
      $this->exec('yum -y install git', 'error');
      $this->exec('yum -y install php-curl', 'error');
                                      
      $this->exec('drush make --prepare-install resources/osf-drupal/osf_drupal.make '.$this->drupal_folder, 'error');
            
      $this->chdir($this->drupal_folder);     
      
      passthru("drush site-install standard --account-name={$this->drupal_admin_username} --account-pass={$this->drupal_admin_password} --db-url=mysql://{$this->sql_app_username}:{$this->sql_app_password}@localhost/{$this->sql_app_database} -y");
      
      $this->cecho("\n", 'WHITE');
          
      $this->chdir($this->currentWorkingDirectory);
      
      // Configuring Apache2 for Drupal      
      $this->span("Configure Apache2 for Drupal...");
      
      $this->cp('resources/osf-drupal/drupal', '/etc/httpd/conf.d/drupal.conf');
      
      // Fix the OSF Web Services path in the apache config file
      $this->sed('/usr/share/drupal', $this->drupal_folder, '/etc/httpd/conf.d/drupal.conf');
      $this->sed('apache2', 'httpd', '/etc/httpd/conf.d/drupal.conf');
      

      $this->span("Restarting Apache2...");
      
      $this->exec('systemctl restart httpd.service');      

      // Install required file for OSF Ontology
      $this->cp('resources/osf-drupal/new.owl', $this->data_folder.'/ontologies/files/new.owl');
      
      // Install the required files for the colorpicker module
      $this->chdir($this->drupal_folder.'/sites/all/libraries/');
      
      $this->mkdir('colorpicker');
      
      $this->chdir('colorpicker');
      
      $this->wget('http://www.eyecon.ro/colorpicker/colorpicker.zip');
      
      $this->exec('unzip colorpicker.zip');
      
      $this->rm('colorpicker.zip');

      // Enable OSF Drupal modules      
      $this->chdir($this->drupal_folder);     
      
      $this->span("Enable OSF Drupal modules...");
      passthru("drush en devel -y");
      passthru("drush en ctools -y");
      passthru("drush en entity -y");
      passthru("drush en entitycache -y");
      passthru("drush en entitycache -y");
      passthru("drush en features -y");
      passthru("drush en libraries -y");
      passthru("drush en jquery_colorpicker -y");
      passthru("drush en views -y");
      passthru("drush en context -y");
      passthru("drush en boxes -y");
      passthru("drush en xautoload -y");
      passthru("drush en search_api -y");
      passthru("drush en search_api_facetapi -y");
      passthru("drush en search_api_page -y");
      passthru("drush en diff -y");
      passthru("drush en entityreference -y");
      passthru("drush en revisioning -y");
      passthru("drush en osf -y");
      passthru("drush en osf_configure -y");
      
      $this->span("You can safely ignore the following 4 errors related to 'illegal choice detected'...", 'warn');
      passthru("drush en osf_searchapi -y");
      passthru("drush en osf_entities -y");
      passthru("drush en osf_permissions -y");
      passthru("drush en osf_fieldstorage -y");
      passthru("drush en osf_export -y");
      passthru("drush en osf_import -y");
      passthru("drush en osf_ontology -y");
      passthru("drush en osf_querybuilder -y");
      passthru("drush en osf_searchprofiles -y");
      passthru("drush en osf_field -y");
      passthru("drush dis overlay -y");
      
      // Create Drupal roles for OSF Drupal
      $this->span("Create Drupal roles for OSF Drupal...");
      
      passthru("drush role-create 'contributor' -y");
      passthru("drush role-create 'owner/curator' -y");
      
      // Change the namespaces.csv file permissions on the server
      $this->chmod($this->drupal_folder.'/sites/all/libraries/OSF-WS-PHP-API/StructuredDynamics/osf/framework/namespaces.csv', '777');

      // Setup OSF Ontology settings
      $this->span("Configure error level settings...");
      passthru('drush vset error_level 1');
      
      // Setup OSF Ontology settings
      $this->span("Configure OSF Ontology settings...");
      
      // Create the schemas folder used by OSF Ontology
      $this->mkdir($this->drupal_folder.'/schemas/', 'warning');
      $this->chmod($this->drupal_folder.'/schemas/', '777');
      $this->chmod($this->data_folder.'/ontologies/', '777', TRUE);
      
      // Configure OSF Entities
      $this->span("Configure OSF Entities settings...");
      
      // Configure OSF SearchAPI settings
      $this->span("Configure OSF Search API settings...");
      
      // Setup default interface
      passthru('drush vset osf_searchapi_settings_interface_name "DefaultSourceInterface"');
      passthru('drush vset osf_searchapi_settings_interface_version "3.0"');
      
      // Create the default search index, page and server
      passthru('drush sqlq \'INSERT INTO `search_api_server` (`id`, `name`, `machine_name`, `description`, `class`, `options`, `enabled`, `status`, `module`) VALUES (1, "OSF Search", "osf_search", "The server handler for OSF SearchAPI", "osf_searchapi_service", "a:1:{s:7:\"network\";s:'.strlen('http://'.$this->osf_web_services_domain.'/ws/').':\"http://'.$this->osf_web_services_domain.'/ws/\";}", 1, 1, NULL);\'');
      passthru('drush sqlq \'INSERT INTO `search_api_index` (`id`, `name`, `machine_name`, `description`, `server`, `item_type`, `options`, `enabled`, `read_only`, `status`, `module`) VALUES (2, "OSF Search Index", "osf_search_index", NULL, "osf_search", "osf", "a:3:{s:14:\"index_directly\";i:0;s:10:\"cron_limit\";s:2:\"50\";s:6:\"fields\";a:2:{s:2:\"id\";a:1:{s:4:\"type\";s:6:\"string\";}s:19:\"search_api_language\";a:1:{s:4:\"type\";s:6:\"string\";}}}", 1, 0, 1, NULL);\'');
      passthru('drush sqlq \'INSERT INTO `search_api_page` (`id`, `index_id`, `path`, `name`, `machine_name`, `description`, `options`, `enabled`, `status`, `module`) VALUES (1, "osf_search_index", "lookup", "OSF Search", "osf_search", "", "a:5:{s:4:\"mode\";s:5:\"terms\";s:6:\"fields\";a:0:{}s:8:\"per_page\";s:2:\"10\";s:12:\"get_per_page\";i:1;s:9:\"view_mode\";s:22:\"search_api_page_result\";}", 1, 1, NULL);\'');
      
      // Make sure the read mode is OFF
      passthru('drush vset osf_OntologySettings_read_mode 0');
      
      // Configure: "Ontologies Files Path folder"
      passthru('drush vset osf_OntologySettings_ontologies_files_folder "'.$this->data_folder.'/ontologies/files/"');

      // Configure: "Ontologies Cache Path folder"
      passthru('drush vset osf_OntologySettings_ontologies_cache_folder "'.$this->data_folder.'/ontologies/structure/"');
      
      // Configure: "Ontologies ironXML Schema Cache Path folder"
      passthru('drush vset osf_OntologySettings_ontologies_ironxml_cache_folder "'.$this->drupal_folder.'/schemas/"');
      
      // Configure: "Ontologies ironJSON Schema Cache Path folder"
      passthru('drush vset osf_OntologySettings_ontologies_ironjson_cache_folder "'.$this->drupal_folder.'/schemas/"');     
      
      $this->chdir($this->currentWorkingDirectory);
      
      $this->span("Creating OSF permissions...");
      
      // Get package info
      $cwdPath = rtrim($this->currentWorkingDirectory, '/');      
      
      // OSF PMT administrative groups
      $this->span("Creating the Drupal administrators group...", 'info');
      $this->exec("pmt --create-group=\"http://{$this->drupal_domain}/role/3/administrator\" --app-id=\"{$this->application_id}\"");
      
      // OSF PMT administrative users
      $this->span("Creating the Drupal administrator user...", 'info');
      $this->exec("pmt --register-user=\"http://{$this->drupal_domain}/user/1\" --register-user-group=\"http://{$this->drupal_domain}/role/3/administrator\"");
      
      // OSF PMT permissions for core datasets
      $this->span("Creating the permissions for the core datasets...", 'info');
      $this->exec("pmt --create-access --access-dataset=\"http://{$this->osf_web_services_domain}/wsf/\" --access-group=\"http://{$this->drupal_domain}/role/3/administrator\" --access-perm-create=\"true\" --access-perm-read=\"true\" --access-perm-update=\"true\" --access-perm-delete=\"true\" --access-all-ws");
      $this->exec("pmt --create-access --access-dataset=\"http://{$this->osf_web_services_domain}/wsf/datasets/\" --access-group=\"http://{$this->drupal_domain}/role/3/administrator\" --access-perm-create=\"true\" --access-perm-read=\"true\" --access-perm-update=\"true\" --access-perm-delete=\"true\" --access-all-ws");
      $this->exec("pmt --create-access --access-dataset=\"http://{$this->osf_web_services_domain}/wsf/ontologies/\" --access-group=\"http://{$this->drupal_domain}/role/3/administrator\" --access-perm-create=\"true\" --access-perm-read=\"true\" --access-perm-update=\"true\" --access-perm-delete=\"true\" --access-all-ws");
      
      // OSF PMT permissions for loaded ontologies
      $this->cp("{$cwdPath}/resources/osf-web-services/ontologies.lst", "{$this->data_folder}/ontologies/");
      $this->sed("file://localhost/data", "file://localhost/".trim($this->data_folder, '/')."/",
        "{$this->data_folder}/ontologies/ontologies.lst", "g");
      $loadedOntologies = explode(' ', file_get_contents("{$this->data_folder}/ontologies/ontologies.lst"));
      
      foreach($loadedOntologies as $loadedOntology) {
        $this->exec("pmt --create-access --access-dataset=\"{$loadedOntology}\" --access-group=\"http://{$this->drupal_domain}/role/3/administrator\" --access-perm-create=\"true\" --access-perm-read=\"true\" --access-perm-update=\"true\" --access-perm-delete=\"true\" --access-all-ws");
      }
      
      
      $this->span("Now that OSF for Drupal is installed, the next steps would be to follow the Initial OSF for Drupal Configuration Guide:\n\n", 'notice');
      $this->span("    http://wiki.opensemanticframework.org/index.php/Initial_OSF_for_Drupal_Configuration\n\n", 'notice');
    }    
        
    
    /**
    * Validate that the OS where the script is running is a Ubuntu instance greater than supported version
    * by the script.
    */
    public static function isWorkingInstaller()
    {
      exec('cat /etc/system-release', $output);

      foreach($output as $line)
      {
        if(stripos($line, 'CentOS') !== FALSE)
        {
          // Validate version
          preg_match('/.*release[\s]([7]+)\..*/i', $line, $matches);

          if(isset($matches[1]) && $matches[1] == "7")
          {
            return(TRUE);
          }
          else
          {
            return(FALSE);
          }
        }
        elseif(stripos($line, 'Red Hat Enterprise Linux Server') !== FALSE)
        {
          // Validate version
          preg_match('/.*release[\s]([7]+)\..*/i', $line, $matches);
          
          if(isset($matches[1]) && $matches[1] == "7")
          {
            return(TRUE);
          }
          else
          {
            return(FALSE);
          }
        }
      }
      
      return(FALSE);
    }
  }
?>
