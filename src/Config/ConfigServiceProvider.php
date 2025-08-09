<?php

declare(strict_types=1);

namespace Engelsystem\Config;

use Engelsystem\Application;
use Engelsystem\Container\ServiceProvider;
use Engelsystem\Models\EventConfig;
use Illuminate\Database\QueryException;
use Symfony\Component\Yaml\Yaml;
use Exception;

class ConfigServiceProvider extends ServiceProvider
{
    protected array $configFiles = ['app.php', 'config.default.php', 'config.php'];
    protected array $configFileDepartmentOauth = [
        'ef' => 'idp_ef_groups.yaml',
        'generic' => 'idp_generic_groups.yaml',
    ];

    // Remember to update ConfigServiceProviderTest, config.default.php, and README.md
    protected array $configVarsToPruneNulls = [
        'themes',
        'tshirt_sizes',
        'headers',
        'header_items',
        'footer_items',
        'locales',
        'contact_options',
    ];

    public function __construct(Application $app, protected ?EventConfig $eventConfig = null)
    {
        parent::__construct($app);
    }

    /**
     * Registers the configuration service and loads configuration files.
     *
     * - Instantiates the Config class and binds it to the application container.
     * - Loads configuration from a list of files, merging them recursively.
     * - Throws an Exception if no configuration is found.
     * - Prunes null values from specified configuration variables.
     * - Loads and parses additional YAML configuration files for department OAuth providers.
     *
     * @throws Exception If no configuration is found after loading files.
     */
    public function register(): void
    {
        $config = $this->app->make(abstract: Config::class);
        $this->app->instance(abstract: Config::class, instance: $config);
        $this->app->instance(abstract: 'config', instance: $config);

        // Load configuration from files
        foreach ($this->configFiles as $file) {
            $file = $this->getConfigPath(path: $file);

            if (!file_exists($file)) {
                continue;
            }

            $configuration = array_replace_recursive(
                $config->get(null),
                require $file
            );

            $config->set(key: $configuration);
        }

        if (empty($config->get(key: null))) {
            throw new Exception('Configuration not found');
        }

        // Prune values with null to remove them
        foreach ($this->configVarsToPruneNulls as $key) {
            $config->set($key, array_filter($config->get($key), function ($v) {
                return !is_null($v);
            }));
        }

        // Working on the groups file
        $oauthDepartmentsArray = [];

        foreach ($this->configFileDepartmentOauth as $provider => $file) {
            $file = $this->getConfigPath(path: $file);

            if (!file_exists(filename: $file)) {
                continue;
            }

            $oauthDepartmentsArray[$provider]['departments'] = Yaml::parseFile(filename: $file);
        }

        $oauthKeyValues = array_replace_recursive(
            $config->get('oauth'),
            $oauthDepartmentsArray
        );
        $config->set(key: 'oauth', value: $oauthKeyValues);
    }

    /**
     * Bootstraps configuration options from the event configuration source.
     *
     * This method retrieves configuration values from the eventConfig model and merges them
     * into the application's configuration repository. If a configuration value is an array
     * and already exists in the config, it will be merged recursively with the existing value.
     * If the eventConfig is not available or a database query exception occurs, the method exits early.
     *
     */
    public function boot(): void
    {
        if (!$this->eventConfig) {
            return;
        }

        /** @var Config $config */
        $config = $this->app->get('config');
        try {
            /** @var EventConfig[] $values */
            $values = $this->eventConfig->newQuery()->get(['name', 'value']);
        } catch (QueryException) {
            return;
        }

        foreach ($values as $option) {
            $data = $option->value;

            if (is_array($data) && $config->has($option->name)) {
                $data = array_replace_recursive(
                    $config->get($option->name),
                    $data
                );
            }

            $config->set($option->name, $data);
        }
    }

    /**
     * Retrieves the full path to the configuration directory or a specific configuration file.
     *
     * @param string $path Optional relative path to a specific configuration file.
     * @return string The absolute path to the configuration directory or file.
     */
    protected function getConfigPath(string $path = ''): string
    {
        return config_path($path);
    }
}
