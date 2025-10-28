<?php

namespace Glpi\Console\Migration;

use DeviceSimcardType;
use Glpi\Asset\Asset;
use Glpi\Asset\AssetDefinition;
use Glpi\Asset\AssetDefinitionManager;
use Glpi\Asset\CustomFieldDefinition;
use Glpi\Asset\CustomFieldType\DropdownType;
use Glpi\Console\AbstractCommand;
use Glpi\DBAL\QueryExpression;
use Glpi\DBAL\QuerySubQuery;
use LineOperator;
use Plugin;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Glpi\Asset\CustomFieldType\StringType;

class SimcardPluginToCustomAssetCommand extends AbstractCommand
{

    private const ASSET_DEFINITION_SYSTEM_NAME = "Simcard";
    private const TABLE_SIMCARDS = "glpi_plugin_simcard_simcards";
    private const TABLE_PHONEOPERATORS = "glpi_plugin_simcard_phoneoperators";
    private const TABLE_SIMCARDSIZES = "glpi_plugin_simcard_simcardsizes";
    private const TABLE_SIMCARDVOLTAGES = "glpi_plugin_simcard_simcardvoltages";
    private const TABLE_SIMCARDTYPES = "glpi_plugin_simcard_simcardtypes";
    private const TABLE_CONFIG = "glpi_plugin_simcard_configs";
    private const TABLE_SIMCARDS_RELATIONS = "glpi_plugin_simcard_simcards_items";
    private const CUSTOM_FIELD_PHONENUMBER = "phonenumber";
    private const CUSTOM_FIELD_PROVIDER = "provider";
    private const CUSTOM_FIELD_PIN1 = "pin1";
    private const CUSTOM_FIELD_PIN2 = "pin2";
    private const CUSTOM_FIELD_PUK1 = "puk1";
    private const CUSTOM_FIELD_PUK2 = "puk2";
    private const CUSTOM_FIELD_IMSI = "imsi";
    private const CUSTOM_FIELD_SIM_TYPE = "sim_type";

    protected function configure()
    {
        parent::configure();
        $this->setName('migration:simcard_plugin_to_custom_asset');
        $this->setDescription('Create custom asset definition and migrate plugin simcards to it');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $no_interaction = $input->getOption('no-interaction');

        if (!$no_interaction) {
            // Ask for confirmation (unless --no-interaction)
            $output->writeln(
                [
                    __('You are about to launch migration of Racks plugin data into GLPI core tables.'),
                    __('It is better to make a backup of your existing data before continuing.'),
                ]
            );

            $this->askForConfirmation(false);
        }

        if (!$this->checkPlugin()) return 1;

        if (!$this->checkCustomAsset()) return 1;
        if (($customAssetDefinition = $this->createCustomAsset()) !== false) {
            $output->writeln('<info>' . __('Custom Asset created') . '</info>');
        } else {
            return 1;
        }

        // finished
        if ($this->input->getOption('without-plugin')) {
            $output->writeln('<info>' . 'Migration finished' . '</info>');
            return 0;
        }

        if (!$this->importSimcards($customAssetDefinition["definition_id"], $customAssetDefinition["custom_field_definition_ids"])) {
            return 1;
        }

        return 0;
    }

    /**
     * @return bool
     */
    private function checkPlugin(): bool
    {
        $check_version = !$this->input->getOption('without-plugin');

        if ($check_version) {
            // check plugin version
            $plugin = new Plugin();
            $plugin->checkPluginState("simcard");

            if (!$plugin->getFromDBbyDir('simcard')) {
                $message = 'Simcard plugin is not part of GLPI plugin list. It has never been installed or has been cleaned.'
                    . ' '
                    . ' Please use --without-plugin option to skip the migration and only create the custom asset definition.';
                $this->output->writeln(
                    [
                        '<error>' . $message . '</error>',
                    ],
                    OutputInterface::VERBOSITY_QUIET
                );
                return false;
            }

            $is_version_ok = '1.2.1' === $plugin->fields['version'] || '2.0.0' === $plugin->fields['version'];
            if (!$is_version_ok) {
                $message = sprintf(
                    'The installed version of the Simcard plugin is %s. It is not compatible with this migration. Please use --without-plugin option to skip the migration and only create the custom asset definition.',
                    $plugin->fields['version'],
                );
                $this->output->writeln(
                    '<error>' . $message . '</error>',
                    OutputInterface::VERBOSITY_QUIET
                );
                return false;
            }

            if (!$this->db->tableExists("glpi_plugin_simcard_simcards")) {
                $this->output->writeln(
                    '<error>' . sprintf('Simcard plugin table "%s" is missing.', "glpi_plugin_simcard_simcards") . '</error>',
                    OutputInterface::VERBOSITY_QUIET
                );
                return false;
            }
        }
        return true;
    }

    /**
     * @return bool
     */
    private function checkCustomAsset(): bool
    {
        $count = $this->db->request([
            "COUNT" => "cnt",
            "FROM" => AssetDefinition::getTable(),
            "WHERE" => [
                "system_name" => self::ASSET_DEFINITION_SYSTEM_NAME
            ]
        ])->current()["cnt"] ?? 0;
        if ($count > 0) {
            $this->output->writeln(
                '<comment>' . sprintf('Custom asset definition "%s" was already created.', self::ASSET_DEFINITION_SYSTEM_NAME) . '</comment>',
                OutputInterface::VERBOSITY_QUIET
            );
            return false;
        }
        return true;
    }

    private function createCustomAsset(): array|bool
    {
        $assetDefinition = new AssetDefinition();
        $progress_bar = new ProgressBar($this->output, 9);
        $progress_bar->start();
        $progress_bar->advance();
        if (!($assetDefinitionId = $assetDefinition->add([
            "system_name" => self::ASSET_DEFINITION_SYSTEM_NAME,
            "label" => "SIM card",
            "icon" => "ti-device-sim",
            "picture" => null,
            "comment" => "",
            "is_active" => 1,
            "capacities" => '[{"name":"Glpi\\Asset\\Capacity\\HasPeripheralAssetsCapacity","config":[]},{"name":"Glpi\\Asset\\Capacity\\HasContractsCapacity","config":[]},{"name":"Glpi\\Asset\\Capacity\\HasDocumentsCapacity","config":[]},{"name":"Glpi\\Asset\\Capacity\\HasInfocomCapacity","config":[]},{"name":"Glpi\\Asset\\Capacity\\HasHistoryCapacity","config":[]},{"name":"Glpi\\Asset\\Capacity\\HasNotepadCapacity","config":[]}]',
            "profiles" => "{}",
            "translations" => "[]",
            "fields_display" => '[{"key":"name","order":0,"field_options":[]},{"key":"states_id","order":1,"field_options":[]},{"key":"custom_phonenumber","order":2,"field_options":{"full_width":"0","required":"0","readonly":"","hidden":""}},{"key":"custom_sim_type","order":3,"field_options":{"full_width":"0","required":"0","readonly":"","hidden":"","multiple":"0"}},{"key":"assets_assetmodels_id","order":4,"field_options":[]},{"key":"custom_provider","order":5,"field_options":{"full_width":"0","required":"0","readonly":"","hidden":"","multiple":"0"}},{"key":"custom_pin1","order":6,"field_options":{"full_width":"0","required":"0","readonly":"","hidden":""}},{"key":"custom_pin2","order":7,"field_options":{"full_width":"0","required":"0","readonly":"","hidden":""}},{"key":"custom_puk1","order":8,"field_options":{"full_width":"0","required":"0","readonly":"","hidden":""}},{"key":"custom_puk2","order":9,"field_options":{"full_width":"0","required":"0","readonly":"","hidden":""}},{"key":"locations_id","order":10,"field_options":[]},{"key":"custom_imsi","order":11,"field_options":{"full_width":"0","required":"0","readonly":"","hidden":""}},{"key":"users_id_tech","order":12,"field_options":[]},{"key":"groups_id_tech","order":13,"field_options":[]},{"key":"contact_num","order":14,"field_options":[]},{"key":"serial","order":15,"field_options":[]},{"key":"contact","order":16,"field_options":[]},{"key":"otherserial","order":17,"field_options":[]},{"key":"users_id","order":18,"field_options":[]},{"key":"groups_id","order":19,"field_options":[]},{"key":"uuid","order":20,"field_options":[]},{"key":"autoupdatesystems_id","order":21,"field_options":[]},{"key":"comment","order":22,"field_options":[]}]',
            "date_creation" => $_SESSION["glpi_currenttime"],
            "date_mod" => $_SESSION["glpi_currenttime"],
        ]))) {
            $this->output->write(PHP_EOL); // Keep progress bar last state and go to next line
            $this->output->writeln(
                '<error>Unable to import custom asset definition</error>',
                OutputInterface::VERBOSITY_NORMAL,
            );
            return false;
        }
        $customFieldDefinitions = [
            [
                "asset_definition_id" => $assetDefinitionId,
                "system_name" => self::CUSTOM_FIELD_PHONENUMBER,
                "label" => "Phone number",
                "type" => StringType::class,
                "field_options" => '{"full_width":"0","required":"0","readonly":"","hidden":""}',
                "itemtype" => null,
                "default_value" => "",
                "translations" => "[]",
            ],
            [
                "asset_definition_id" => $assetDefinitionId,
                "system_name" => self::CUSTOM_FIELD_PROVIDER,
                "label" => "Provider",
                "type" => DropdownType::class,
                "field_options" => '{"full_width":"0","required":"0","readonly":"","hidden":"","multiple":"0"}',
                "itemtype" => "LineOperator",
                "default_value" => null,
                "translations" => "[]",
            ],
            [
                "asset_definition_id" => $assetDefinitionId,
                "system_name" => self::CUSTOM_FIELD_PIN1,
                "label" => "PIN 1",
                "type" => StringType::class,
                "field_options" => '{"full_width":"0","required":"0","readonly":"","hidden":""}',
                "itemtype" => null,
                "default_value" => "",
                "translations" => "[]",
            ],
            [
                "asset_definition_id" => $assetDefinitionId,
                "system_name" => self::CUSTOM_FIELD_PIN2,
                "label" => "PIN 2",
                "type" => StringType::class,
                "field_options" => '{"full_width":"0","required":"0","readonly":"","hidden":""}',
                "itemtype" => null,
                "default_value" => "",
                "translations" => "[]",
            ],
            [
                "asset_definition_id" => $assetDefinitionId,
                "system_name" => self::CUSTOM_FIELD_PUK1,
                "label" => "PUK 1",
                "type" => StringType::class,
                "field_options" => '{"full_width":"0","required":"0","readonly":"","hidden":""}',
                "itemtype" => null,
                "default_value" => "",
                "translations" => "[]",
            ],
            [
                "asset_definition_id" => $assetDefinitionId,
                "system_name" => self::CUSTOM_FIELD_PUK2,
                "label" => "PUK 2",
                "type" => StringType::class,
                "field_options" => '{"full_width":"0","required":"0","readonly":"","hidden":""}',
                "itemtype" => null,
                "default_value" => "",
                "translations" => "[]",
            ],
            [
                "asset_definition_id" => $assetDefinitionId,
                "system_name" => self::CUSTOM_FIELD_IMSI,
                "label" => "IMSI",
                "type" => StringType::class,
                "field_options" => '{"full_width":"0","required":"0","readonly":"","hidden":""}',
                "itemtype" => null,
                "default_value" => "",
                "translations" => "[]",
            ],
            [
                "asset_definition_id" => $assetDefinitionId,
                "system_name" => self::CUSTOM_FIELD_SIM_TYPE,
                "label" => "SIM type",
                "type" => DropdownType::class,
                "field_options" => '{"full_width":"0","required":"0","readonly":"","hidden":"","multiple":"0"}',
                "itemtype" => "DeviceSimcardType",
                "default_value" => null,
                "translations" => "[]",
            ],
        ];
        $customFieldDefinitionIds = [];
        $customFieldDefinition = new CustomFieldDefinition();
        foreach ($customFieldDefinitions as $customFieldDefinitionData) {
            $progress_bar->advance();
            $customFieldDefinitionId = $customFieldDefinition->add($customFieldDefinitionData);
            if ($customFieldDefinitionId === false) {
                $this->output->write(PHP_EOL);
                $this->output->writeln(
                    sprintf('<error>Unable to create custom field "%s"</error>', $customFieldDefinitionData["system_name"]),
                    OutputInterface::VERBOSITY_NORMAL,
                );
                return false;
            } else {
                $customFieldDefinitionIds[$customFieldDefinitionData["system_name"]] = $customFieldDefinitionId;
            }
        }
        $progress_bar->finish();
        return ["definition_id" => $assetDefinitionId, "custom_field_definition_ids" => $customFieldDefinitionIds];
    }

    private function importSimcards(int $definitionId, array $customFieldIds): bool
    {
        $classname = 'Glpi\\CustomAsset\\' . self::ASSET_DEFINITION_SYSTEM_NAME . 'Asset';
        AssetDefinitionManager::getInstance()
            ->autoloadClass($classname);
        if (!class_exists($classname)) {
            return false;
        }

        $simcardIterator = $this->db->request([
            "FROM" => self::TABLE_SIMCARDS,
            "WHERE" => [
                "is_deleted" => 0
            ]
        ]);
        $notExistingProviderIterator = $this->db->request([
            "FROM" => self::TABLE_PHONEOPERATORS,
            "WHERE" => [
                "NOT" => [
                    "name" => new QuerySubQuery([
                        "SELECT" => "name",
                        "FROM" => LineOperator::getTable(),
                    ])
                ]
            ]
        ]);
        $notExistingSizeIterator = $this->db->request([
            "FROM" => self::TABLE_SIMCARDSIZES,
            "WHERE" => [
                "NOT" => [
                    "name" => new QuerySubQuery([
                        "SELECT" => "name",
                        "FROM" => DeviceSimcardType::getTable(),
                    ])
                ]
            ]
        ]);
        $progress_bar = new ProgressBar(
            $this->output,
            $simcardIterator->count() +
            $notExistingSizeIterator->count() +
            $notExistingProviderIterator->count()
        );
        $progress_bar->start();
        $progress_bar->advance();

        /**
         * @var $classname Asset
         */
        $asset = new $classname();
        $simcardType = new DeviceSimcardType();
        $lineOperator = new LineOperator();
        $simcard = new AssetDefinition();

        foreach ($notExistingSizeIterator as $row) {
            $simcardType->add([
                "name" => $row["name"]
            ]);
            $progress_bar->advance();
        }
        foreach ($notExistingProviderIterator as $row) {
            $lineOperator->add([
                "name" => $row["name"],
                "comment" => null,
                "mcc" => 0,
                "mnc" => 0,
                "entities_id" => 0,
                "is_recursive" => 1
            ]);
            $progress_bar->advance();
        }

        foreach ($simcardIterator as $row) {
            $operatorId = null;
            if ($row["plugin_simcard_phoneoperators_id"] !== null) {
                $operatorId = $this->db->request([
                    "SELECT" => [self::TABLE_PHONEOPERATORS . ".id"],
                    "FROM" => $lineOperator::getTable(),
                    "INNER JOIN" => [
                        self::TABLE_PHONEOPERATORS => [
                            "ON" => [
                                $lineOperator::getTable() => "name",
                                self::TABLE_PHONEOPERATORS => "name"
                            ]
                        ]
                    ],
                    "WHERE" => [
                        self::TABLE_PHONEOPERATORS . ".id" => $row["plugin_simcard_phoneoperators_id"]
                    ]
                ])->current()["id"] ?? null;
            }
            $sizeId = null;
            if ($row["plugin_simcard_simcardsizes_id"] !== null) {
                $sizeId = $this->db->request([
                    "SELECT" => [self::TABLE_SIMCARDSIZES . ".id"],
                    "FROM" => $simcardType::getTable(),
                    "INNER JOIN" => [
                        self::TABLE_SIMCARDSIZES => [
                            "ON" => [
                                $simcardType::getTable() => "name",
                                self::TABLE_SIMCARDSIZES => "name"
                            ]
                        ]
                    ],
                    "WHERE" => [
                        self::TABLE_SIMCARDSIZES . ".id" => $row["plugin_simcard_simcardsizes_id"]
                    ]
                ])->current()["id"] ?? null;
            }
            $id = $asset->add([
                "name" => $row["name"],
                "custom_pin1" => $row["pin"] ?? "",
                "custom_pin2" => $row["pin2"] ?? "",
                "custom_puk1" => $row["puk"] ?? "",
                "custom_puk2" => $row["puk2"] ?? "",
                "custom_imsi" => $row["imsi"] ?? "",
                "custom_phonenumber" => $row["phonenumber"] ?? "",
                "custom_provider" => $operatorId,
                "custom_sim_type" => $sizeId,
                "entities_id" => $row["entities_id"] ?? 0,
                "manufacturers_id" => 0,
                "locations_id" => $row["locations_id"] ?? 0,
                "users_id_tech" => $row["users_id_tech"] ?? 0,
                "users_id" => $row["users_id"] ?? 0,
                "states_id" => $row["states_id"] ?? 0,
                "otherserial" => $row["otherserial"] ?? "",
                "assets_assetmodels_id" => 0,
                "assets_assettypes_id" => 0,
                "uuid" => "",
                "comment" => $row["comment"] ?? "",
                "serial" => $row["serial"] ?? "",
                "contact" => "",
                "contact_num" => "",
                "is_deleted" => 0,
                "is_template" => empty($row["template_name"]) ? 0 : 1,
                "is_dynamic" => 0,
                "template_name" => empty($row["template_name"]) ? null : $row["template_name"],
                "autoupdatesystems_id" => 0,
            ]);
            $progress_bar->advance();


            $simcardRelations = $this->db->request([
                "FROM" => self::TABLE_SIMCARDS_RELATIONS,
                "WHERE" => [
                    "plugin_simcard_simcards_id" => $row["id"]
                ]
            ]);
            // TODO: RELATIONS

        }

        return true;
    }

}