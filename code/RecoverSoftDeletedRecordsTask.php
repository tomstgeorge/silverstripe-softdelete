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
    protected static string $description = 'Helps you to track and potentially recover or clean up any soft deleted record';

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
            $output->writeln("Please choose any of the following class and pass it as 'class' in the url.");

            foreach ($classes as $cl) {
                $output->writeForAnsi(sprintf("  - %s", $cl), true);
                $output->writeForHtml(sprintf("<a href=\"/dev/tasks/RecoverSoftDeletedRecordsTask?class=%s\">%s</a>", $cl, $cl), false);
            }

            return Command::SUCCESS;
        }

        if (!in_array($selectedClass, $classes)) {
            $output->writeln(sprintf("<error>%s is not valid</error>", $selectedClass));

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

        if ($recover) {
            if ($recover == 'all' || $cleanup) {
                // keep all
            } else {
                $toRecover = array_map('trim', explode(',', $recover));
            }
        }

        SoftDeletable::$disable = true;
        $records = $selectedClass::get()->where('Deleted IS NOT NULL');

        if (!$records->count()) {
            $output->writeln('No soft deleted records');
        }

        foreach ($records as $record) {
            if ($recover == 'all' || ($recover && in_array($record->ID, $toRecover))) {
                $record->undoDelete();
                $output->writeln(sprintf("<info>%s (#%s) has been recovered</info>", $record->getTitle(), $record->ID));
            } elseif ($cleanup) {
                $output->writeln(sprintf("Deleting %s", $record->getTitle()));
                $record->delete();
            } else {
                $DeletedBy = $record->DeletedBy();
                $Deleter = $DeletedBy ? $DeletedBy->getTitle() : "Unknown";
                $output->writeln(sprintf("%s (#%s) has been deleted at %s by %s", $record->getTitle(), $record->ID, $record->Deleted, $Deleter));
            }
        }

        if ($recover) {
            $output->writeln('Recovery complete');
        } elseif ($cleanup) {
            $output->writeln('Cleanup complete');
        } else {
            $output->writeln("Recover all of of list of records by passing ?recover=all or ?recover=id,id2,id3 in the url or clean them by passing ?cleanup=1");
        }

        return Command::SUCCESS;
    }

}
