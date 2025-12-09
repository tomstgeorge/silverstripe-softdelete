<?php

use SilverStripe\Dev\BuildTask;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\PolyExecution\PolyOutput;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;

/**
 * @author Koala
 */
class RecoverSoftDeletedRecordsTask extends BuildTask
{
    /**
     * @inheritDoc
     */
    protected static string $commandName  = 'RecoverSoftDeletedRecordsTask';

    /**
     * @inheritDoc
     */
    protected string $title = 'Recover or Clean Soft Deleted Records';

    /**
     * @inheritDoc
     */
    protected static  string $description = 'Helps you to track and potentially recover or clean up any soft deleted record';

    /**
     * Setup command options
     *
     * @return array
     */
    public function getOptions(): array
    {
        return [
            new InputOption('class', null, InputOption::VALUE_REQUIRED, 'The class to recover/clean'),
            new InputOption('recover', null, InputOption::VALUE_OPTIONAL, 'Recover records (all or comma-separated IDs)', false),
            new InputOption('cleanup', null, InputOption::VALUE_NONE, 'Permanently delete soft-deleted records'),
        ];
    }

    /**
     * @inheritDoc
     */
    protected function execute(InputInterface $input, PolyOutput $output): int
    {
        $classes = SoftDeletable::listSoftDeletableClasses();

        if (empty($classes)) {
            $output->writeln('<error>No softDeletable classes</error>');
            return Command::FAILURE;
        }

        $selectedClass = $input->getOption('class');
        $recover = $input->getOption('recover');
        $cleanup = $input->getOption('cleanup');

        if (!$selectedClass) {
            $output->writeln('Please choose any of the following classes:');
            foreach ($classes as $cl) {
                $output->writeln("  - {$cl}");
            }
            $output->writeln("\nUsage: sake tasks:RecoverSoftDeletedRecordsTask --class=ClassName [--recover=all|id1,id2] [--cleanup]");
            return Command::SUCCESS;
        }

        if (!in_array($selectedClass, $classes)) {
            $output->writeln("<error>{$selectedClass} is not valid</error>");
            return Command::FAILURE;
        }

        if ($recover && $cleanup) {
            $output->writeln('<error>Cannot recover and cleanup at the same time</error>');
            return Command::FAILURE;
        }

        if ($cleanup) {
            SoftDeletable::$prevent_delete = false;
        }

        $toRecover = [];
        if ($recover && $recover !== 'all') {
            $toRecover = array_map('trim', explode(',', $recover));
        }

        SoftDeletable::$disable = true;
        $records = $selectedClass::get()->where('Deleted IS NOT NULL');

        if (!$records->count()) {
            $output->writeln('No soft deleted records');
            return Command::SUCCESS;
        }

        foreach ($records as $record) {
            if ($recover === 'all' || ($recover && in_array($record->ID, $toRecover))) {
                $record->undoDelete();
                $output->writeln("<info>{$record->getTitle()} (#{$record->ID}) has been recovered</info>");
            } elseif ($cleanup) {
                $output->writeln("Deleting {$record->getTitle()}");
                $record->delete();
            } else {
                $DeletedBy = $record->DeletedBy();
                $Deleter = $DeletedBy ? $DeletedBy->getTitle() : "Unknown";
                $output->writeln("{$record->getTitle()} (#{$record->ID}) deleted at {$record->Deleted} by {$Deleter}");
            }
        }

        if ($recover) {
            $output->writeln('<info>Recovery complete</info>');
        } elseif ($cleanup) {
            $output->writeln('<info>Cleanup complete</info>');
        } else {
            $output->writeln("\nTo recover: --recover=all or --recover=id1,id2");
            $output->writeln("To cleanup: --cleanup");
        }

        return Command::SUCCESS;
    }
}
