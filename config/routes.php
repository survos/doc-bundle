<?php

// in bundle
/*
survos_doc:
resource: '@SurvosDocBundle/config/routes.php'
    prefix: '/doc'
*/
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

return function (RoutingConfigurator $routes) {
    $routes->add('survos_doc', '/doc')
        ->controller('survos.doc.odoc_controller')
    ;
    $routes->add('odoc_providers', '/odoc_providers')
        ->controller('survos.doc.odoc_controller::providers')

    ;
};
