<?php

namespace Vamsi\Vex\Command;

use GuzzleHttp\Client;
use GuzzleHttp\Pool;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\TransferStats;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class VexCommand extends Command
{
    protected $statsCollector;
    
    protected function configure()
    {
        $this
            ->setName('vex')
            ->setDescription('make the specified requests')
            ->setHelp('This command sends the specified requests to the specified URL')
            ->addArgument('url', InputArgument::REQUIRED, 'The URL to which the requests should be sent')
            ->addArgument('n', InputArgument::OPTIONAL, 'Number of requests to be made', 1)
            ->addArgument('c', InputArgument::OPTIONAL, 'Concurrency', 1)
            ->addOption('method', 'm', InputOption::VALUE_OPTIONAL, 'HTTP Method', 'GET')
            ->addOption('headers', 'H', InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY, 'Headers', null)
            ->addOption('body', 'd', InputOption::VALUE_OPTIONAL, 'Request body', null)
            ->addOption('no-report', null, InputOption::VALUE_NONE, 'Disable the detailed report output')
            ->addOption('format', 'f', InputOption::VALUE_OPTIONAL, 'Output format (text, json)', 'text');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->statsCollector = new StatsCollector();
        
        $url = $input->getArgument('url');
        $number_of_requests = $input->getArgument('n');
        $concurrency = $input->getArgument('c');
        $http_method = $input->getOption('method');
        $headers = $input->getOption('headers');
        $body = $input->getOption('body');
        $format = $input->getOption('format');

        $output->writeln("Sending $number_of_requests requests with $concurrency Concurrency");
        $client = new Client([
            'on_stats' => function (TransferStats $stats) {
                $time = $stats->getTransferTime();
                $response = $stats->getResponse();
                
                if ($response) {
                    $statusCode = $response->getStatusCode();
                    $this->statsCollector->addResponse($statusCode, $time);
                }
            },
        ]);

        $progress = new ProgressBar($output, $number_of_requests);

        $progress->setFormat(' %current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s% %memory:6s%');
        $progress->start();
        $requests = function ($total) use ($url, $http_method, $headers, $body) {
            $uri = $url;
            for ($i = 0; $i < $total; $i++) {
                yield new Request($http_method, $uri, $headers, $body);
            }
        };

        $pool = new Pool($client, $requests($number_of_requests), [
            'concurrency' => $concurrency,
            'fulfilled'   => function ($response, $index) use ($progress) {
                $progress->advance();
            },
            'rejected' => function ($reason, $index) use ($progress) {
                $this->statsCollector->addError($reason);
                $progress->advance();
            },
        ]);

        $promise = $pool->promise();
        $promise->wait();
        $progress->finish();
        $output->writeln('');
        $output->writeln('Done!');
        
        // Always display report (text format by default)
        // Only skip if explicitly disabled with --no-report option
        if (!$input->getOption('no-report')) {
            $this->displayReport($output, $format);
        }
        
        return 0;
    }
    
    protected function displayReport(OutputInterface $output, string $format = 'text'): void
    {
        if ($format === 'json') {
            $this->displayJsonReport($output);
            return;
        }
        
        // Text format (default)
        $output->writeln('');
        $output->writeln('┌─────────────────────────────────────┐');
        $output->writeln('│ <fg=bright-green;options=bold>RESPONSE TIME STATISTICS</> │');
        $output->writeln('└─────────────────────────────────────┘');
        
        $timeStats = $this->statsCollector->getTimeStats();
        if (empty($timeStats)) {
            $output->writeln('  No successful responses received.');
        } else {
            $output->writeln(sprintf('  • Min:   <fg=cyan;options=bold>%.3fs</>', $timeStats['min']));
            $output->writeln(sprintf('  • Max:   <fg=cyan;options=bold>%.3fs</>', $timeStats['max']));
            $output->writeln(sprintf('  • Avg:   <fg=cyan;options=bold>%.3fs</>', $timeStats['avg']));
            $output->writeln(sprintf('  • Total: <fg=cyan;options=bold>%.3fs</>', $timeStats['total']));
            
            // Add request rate calculation
            if ($timeStats['total'] > 0) {
                $requestsPerSecond = $this->statsCollector->getTotalSuccessfulRequests() / $timeStats['total'];
                $output->writeln(sprintf('  • Rate:  <fg=green;options=bold>%.2f</> requests/second', $requestsPerSecond));
            }
        }
        
        $output->writeln('');
        $output->writeln('┌─────────────────────────────────────┐');
        $output->writeln('│ <fg=bright-green;options=bold>STATUS CODE DISTRIBUTION</> │');
        $output->writeln('└─────────────────────────────────────┘');
        
        $statusDistribution = $this->statsCollector->getStatusDistribution();
        if (empty($statusDistribution)) {
            $output->writeln('  No status codes recorded.');
        } else {
            $table = new Table($output);
            $table->setHeaderTitle('Response Statistics by Status Code');
            $table->setHeaders(['Status', 'Count', 'Percentage', 'Min Time', 'Max Time', 'Avg Time']);
            $table->setStyle('box');
            
            // Sort by status code
            ksort($statusDistribution);
            
            foreach ($statusDistribution as $code => $data) {
                $statusColor = $this->getStatusCodeColor($code);
                $codeDisplay = sprintf('<fg=%s;options=bold>%s</>', $statusColor, $code);
                $percentage = sprintf('<fg=default;options=bold>%.1f%%</>', $data['percentage']);
                $table->addRow([
                    $codeDisplay,
                    $data['count'],
                    $percentage,
                    sprintf('%.3fs', $data['times']['min']),
                    sprintf('%.3fs', $data['times']['max']),
                    sprintf('%.3fs', $data['times']['avg']),
                ]);
            }
            
            $table->render();
            
            // Add summary chart if there are multiple status codes
            if (count($statusDistribution) > 1) {
                $this->renderStatusCodeBarChart($output, $statusDistribution);
            }
        }
        
        $errors = $this->statsCollector->getErrors();
        if (!empty($errors)) {
            $output->writeln('');
            $output->writeln('┌─────────────────────────────────────┐');
            $output->writeln('│ <fg=bright-red;options=bold>ERRORS</> │');
            $output->writeln('└─────────────────────────────────────┘');
            
            $table = new Table($output);
            $table->setHeaderTitle('Error Summary');
            $table->setHeaders(['Error', 'Count', 'Percentage']);
            $table->setStyle('box');
            
            foreach ($errors as $error => $data) {
                $percentage = sprintf('<fg=default;options=bold>%.1f%%</>', $data['percentage']);
                $table->addRow([
                    $error,
                    $data['count'],
                    $percentage,
                ]);
            }
            
            $table->render();
        }
    }
    
    private function renderStatusCodeBarChart(OutputInterface $output, array $statusDistribution): void
    {
        $output->writeln("");
        $output->writeln("  <fg=bright-white;options=bold>Status Code Distribution by Group</>");
        
        $maxBarWidth = 40;
        
        // Group status codes by class (2xx, 3xx, etc.)
        $codeGroups = [
            '2xx' => ['count' => 0, 'color' => 'green'],
            '3xx' => ['count' => 0, 'color' => 'blue'],
            '4xx' => ['count' => 0, 'color' => 'yellow'],
            '5xx' => ['count' => 0, 'color' => 'red']
        ];
        
        foreach ($statusDistribution as $code => $data) {
            $groupKey = floor($code / 100) . 'xx';
            if (isset($codeGroups[$groupKey])) {
                $codeGroups[$groupKey]['count'] += $data['count'];
            }
        }
        
        $totalCount = array_sum(array_column($codeGroups, 'count'));
        
        foreach ($codeGroups as $group => $data) {
            if ($data['count'] <= 0) continue;
            
            $percentage = ($data['count'] / $totalCount) * 100;
            $barWidth = ($percentage / 100) * $maxBarWidth;
            
            $bar = str_repeat('█', max(1, (int)$barWidth));
            $output->writeln(sprintf(
                '  %s: <fg=%s>%s</> %d (%.1f%%)',
                $group,
                $data['color'],
                $bar,
                $data['count'],
                $percentage
            ));
        }
        
        $output->writeln('');
    }
    
    protected function displayJsonReport(OutputInterface $output): void
    {
        $timeStats = $this->statsCollector->getTimeStats();
        $requestsPerSecond = 0;
        
        if (!empty($timeStats) && $timeStats['total'] > 0) {
            $requestsPerSecond = $this->statsCollector->getTotalSuccessfulRequests() / $timeStats['total'];
        }
        
        // Create status code group summary
        $codeGroups = [
            '2xx' => 0,
            '3xx' => 0,
            '4xx' => 0,
            '5xx' => 0
        ];
        
        foreach ($this->statsCollector->getStatusDistribution() as $code => $data) {
            $groupKey = floor($code / 100) . 'xx';
            if (isset($codeGroups[$groupKey])) {
                $codeGroups[$groupKey] += $data['count'];
            }
        }
        
        $report = [
            'summary' => [
                'total_requests' => $this->statsCollector->getTotalRequests(),
                'successful_requests' => $this->statsCollector->getTotalSuccessfulRequests(),
                'failed_requests' => $this->statsCollector->getTotalFailedRequests(),
                'requests_per_second' => round($requestsPerSecond, 2)
            ],
            'time_stats' => $timeStats,
            'status_groups' => $codeGroups,
            'status_distribution' => $this->statsCollector->getStatusDistribution(),
            'errors' => $this->statsCollector->getErrors(),
        ];
        
        $output->writeln(json_encode($report, JSON_PRETTY_PRINT));
    }
    
    protected function getStatusCodeColor(int $code): string
    {
        if ($code >= 500) {
            return 'red';
        } elseif ($code >= 400) {
            return 'yellow';
        } elseif ($code >= 300) {
            return 'blue';
        } else {
            return 'green';
        }
    }
}
