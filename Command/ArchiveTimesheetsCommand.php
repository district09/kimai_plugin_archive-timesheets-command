<?php

/*
 * This file is part of the Kimai ArchiveTimesheetsCommandBundle.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\ArchiveTimesheetsCommandBundle\Command;

use App\Repository\Query\TimesheetQuery;
use App\Repository\TimesheetRepository;
use Doctrine\ORM\EntityManagerInterface;
use Pagerfanta\Exception\OutOfRangeCurrentPageException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class ArchiveTimesheetsCommand extends Command
{
    protected static $defaultName = 'kimai:archive:timesheets';

    /**
     * @var TimesheetRepository
     */
    protected $timesheetRepository;

    /**
     * @var EntityManagerInterface
     */
    protected $entityManager;

    public function __construct(TimesheetRepository $timesheetRepository, EntityManagerInterface $entityManager)
    {
        parent::__construct();
        $this->timesheetRepository = $timesheetRepository;
        $this->entityManager = $entityManager;
    }

    protected function configure()
    {
        $this
            ->setDescription(
                'Archive (remove) timesheets older than the given preserve '
              . 'period.'
            )
            ->addOption(
                'preserve-period',
                'p',
                InputOption::VALUE_OPTIONAL,
                'The period for which to preserve the timesheets, '
                . 'in the PHP DateInterval format '
                . '(http://php.net/manual/en/dateinterval.construct.php'
                . '#refsect1-dateinterval.construct-parameters). '
                . 'Defaults to 1 year.',
                'P1Y'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $style = new SymfonyStyle($input, $output);
        try {
            $preservePeriod = $input->getOption('preserve-period');
            $period = new \DateInterval($preservePeriod);
        } catch (\Exception $exception) {
            $style->error(sprintf(
                '%s is not a valid period format. See '
                . 'http://php.net/manual/en/dateinterval.construct.php'
                . '#refsect1-dateinterval.construct-parameters for more information.',
                $preservePeriod
            ));

            // Return an exit code higher than 0.
            return 1;
        }

        // The TimesheetQuery sets the time component of the end date to
        // 23:59:59, so we substract another day.
        $end = (new \DateTime())->sub($period)->sub(new \DateInterval('P1D'));
        $query = new TimesheetQuery();
        $query->setEnd($end);
        // Do it in batch, so we don't have to load all timesheets into memory
        // at once.
        $query->setPageSize(50);
        $pagedTimesheets = $this->timesheetRepository->getPagerfantaForQuery($query);
        $pagedTimesheets->setNormalizeOutOfRangePages(false);
        $pagedTimesheets->setAllowOutOfRangePages(false);
        $progressBar = new ProgressBar($output, $pagedTimesheets->count());

        $this->entityManager->beginTransaction();
        while (true) {
            try {
                $timesheets = $pagedTimesheets->getCurrentPageResults();
                foreach ($timesheets as $timesheet) {
                    // Metafields aren't cascaded, remove them manually.
                    foreach ($timesheet->getMetaFields() as $meta) {
                        $this->entityManager->remove($meta);
                    }
                    $this->entityManager->remove($timesheet);
                    $progressBar->advance();
                }
                $pagedTimesheets->setCurrentPage($pagedTimesheets->getCurrentPage() + 1);
            }
            catch (OutOfRangeCurrentPageException $ex) {
                break;
            }
            catch (\Exception $ex) {
                $this->entityManager->rollback();
                throw $ex;
            }

        }

        $this->entityManager->flush();
        $this->entityManager->commit();

        return 0;
    }
}
