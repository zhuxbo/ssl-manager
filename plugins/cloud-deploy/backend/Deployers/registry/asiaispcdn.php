<?php

use Plugins\CloudDeploy\Deployers\Asiaispcdn\AsiaispcdnDeployer;
use Plugins\CloudDeploy\Deployers\Asiaispcdn\AsiaispcdnProvider;
use Plugins\CloudDeploy\Deployers\Registry;

return function (Registry $registry): void {
    $registry->registerProvider(new AsiaispcdnProvider);
    $registry->registerDeployer('asiaispcdn', 'cdn', fn () => new AsiaispcdnDeployer);
};
