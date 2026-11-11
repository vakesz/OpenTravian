<?php
if(!(php_sapi_name() == 'cli')){
    exit("CLI Only!");
}
require __DIR__ . "/env.php";
require dirname(__DIR__, 2) . "/include/bootstrap.php";
use Core\Config;
use Core\Database\DB;
use Model\InstallerModel;
mt_srand(make_seed());
class shell_installer
{
    public function __construct($password)
    {
        echo 'before install.' . PHP_EOL;
        if (Config::getInstance()->dynamic->installed) {
            echo 'Installation is completed.' . PHP_EOL;
        } else {
            echo 'Begin installation...';
            ini_set('memory_limit', '1536M');
            set_time_limit(0);
            //fclose(fopen("installation.log", "w"));
            $installer = new InstallerModel();
            $installer->mapToArray();
            $installer->createRestOfTheMap();
            $installer->pushMapToDB();
            $installer->finalize($password);
            $db = DB::getInstance();
            $db->query("UPDATE odata SET lasttrain=" . Config::getProperty('game', 'start_time'));
            exit();
        }
    }
}
$password = trim($argv[1] ?? '');
if ($password === '') {
    fwrite(STDERR, 'Usage: php install.php <admin-password>' . PHP_EOL);
    exit(1);
}
new shell_installer($password);
