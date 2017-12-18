<?php
namespace Dfn\Bundle\OroCronofyBundle\Command;

use Dfn\Bundle\OroCronofyBundle\Async\Topics;
use Doctrine\ORM\EntityManager;

use Oro\Bundle\CronBundle\Command\CronCommandInterface;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class SyncCommand
 * @package Dfn\Bundle\OroCronofyBundle\Command
 */
class SyncCommand extends ContainerAwareCommand implements CronCommandInterface
{
    /**
     * Run nightly at 2:00 am
     * @return string
     */
    public function getDefaultDefinition()
    {
        return '0 2 * * *';
    }

    /**
     *
     */
    protected function configure()
    {
        $this
            ->setName('oro:cron:cronofy-sync')
            ->setDescription('Syncs active calendar origins with Cronofy.');
    }

    /**
     * @return bool
     */
    public function isEnabled()
    {
        $config = $this->getContainer()->get('oro_config.global');
        //Confirm cronofy is configured.
        if ($config->get('dfn_oro_cronofy.client_id') &&
            $config->get('dfn_oro_cronofy.client_secret')) {
            return true;
        } else {
            return false;
        }

    }

    /**
     * @return bool
     */
    public function isActive()
    {
        return $this->isEnabled();
    }

    /**
     * Send push and pull messages for all active origins
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        /** @var EntityManager $em */
        $em = $this->getContainer()->get('doctrine.orm.entity_manager');

        if (!$em) {
            return;
        }

        $messageProducer = $this->getContainer()->get('oro_message_queue.message_producer');

        $origins = $em->getRepository('DfnOroCronofyBundle:CalendarOrigin')->findBy([
            'isActive' => true
        ]);

        foreach ($origins as $origin) {
            $messageProducer->send(
                Topics::SYNC,
                [
                    "origin_id" => $origin->getId(),
                    "action" => "push"
                ]
            );
            $messageProducer->send(
                Topics::SYNC,
                [
                    "origin_id" => $origin->getId(),
                    "action" => "pull"
                ]
            );
        }
    }
}
