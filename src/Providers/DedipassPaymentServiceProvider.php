<?php

namespace Azuriom\Plugin\DedipassPayment\Providers;

use Azuriom\Extensions\Plugin\BasePluginServiceProvider;
use Azuriom\Plugin\DedipassPayment\DediPassMethod;

class DedipassPaymentServiceProvider extends BasePluginServiceProvider
{
    /**
     * Register any plugin services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap any plugin services.
     *
     * @return void
     */
    public function boot()
    {
        if (! plugins()->isEnabled('shop')) {
            logger()->warning('Dedipass plugin need the shop plugin to work !');

            return;
        }

        $this->loadViews();

        $this->loadTranslations();

        payment_manager()->registerPaymentMethod('dedipass', DediPassMethod::class);
    }
}
