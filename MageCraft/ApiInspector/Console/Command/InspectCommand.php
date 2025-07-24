<?php
namespace MageCraft\ApiInspector\Console\Command;

use Magento\Framework\App\State;
use MageCraft\ApiInspector\Helper\ApiExportHelper;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class InspectCommand extends Command
{
    private $apiHelper;
    private $state;

    public function __construct(ApiExportHelper $apiHelper, State $state)
    {
        $this->apiHelper = $apiHelper;
        $this->state = $state;
        parent::__construct();
    }

    protected function configure()
    {
        $this->setName('magecraft:api:inspect')
            ->setDescription('Inspect Magento APIs (REST or GraphQL)')
            ->addArgument('type', InputArgument::REQUIRED, 'API type (rest or graphql)');
    }

    /**
     * Executes the CLI command to inspect Magento APIs.
     *
     * Currently, it supports only the `rest` API type. When executed with `rest` as the
     * argument, it generates a Postman-compatible collection of all Magento 2 REST APIs
     * using the `ApiExportHelper` and outputs the path to the generated JSON file.
     *
     * Steps:
     * - Sets the application area code to 'adminhtml' (if not already set).
     * - Validates the 'type' argument.
     * - If valid, delegates API export to the helper class.
     * - Outputs the result (JSON file path or error message).
     *
     * @param InputInterface $input CLI input object containing arguments
     * @param OutputInterface $output CLI output object to write messages
     *
     * @return int Command exit code (SUCCESS or FAILURE)
     */

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        try {
            $this->state->setAreaCode('adminhtml');
        } catch (\Magento\Framework\Exception\LocalizedException $e) {
            // area already set
        }

        $type = strtolower($input->getArgument('type'));

        if (!in_array($type, ['rest'])) {
            $output->writeln("<error>Invalid type: $type. Only 'rest' is supported for now.</error>");
            return Command::FAILURE;
        }
        $result = '';
        switch ($type){
            case 'rest':
                $result = $this->apiHelper->getRestApiCollection();
                break;
        }
        $output->writeln(json_encode($result, JSON_PRETTY_PRINT));
        return Command::SUCCESS;
    }
}
