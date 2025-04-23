<?php

namespace Vamsi\Vex\Command;

class StatsCollector
{
    private $responseData = [];
    private $statusCodes = [];
    private $errors = [];
    private $totalRequests = 0;
    private $successfulRequests = 0;
    private $failedRequests = 0;
    
    public function getTotalRequests(): int
    {
        return $this->totalRequests;
    }
    
    public function getTotalSuccessfulRequests(): int
    {
        return $this->successfulRequests;
    }
    
    public function getTotalFailedRequests(): int
    {
        return $this->failedRequests;
    }
    
    public function addResponse(int $code, float $time): void
    {
        $this->totalRequests++;
        $this->successfulRequests++;
        
        $this->responseData[] = [
            'code' => $code,
            'time' => $time
        ];
        
        // Track by status code
        if (!isset($this->statusCodes[$code])) {
            $this->statusCodes[$code] = [
                'count' => 0,
                'times' => []
            ];
        }
        
        $this->statusCodes[$code]['count']++;
        $this->statusCodes[$code]['times'][] = $time;
    }
    
    public function addError($reason): void
    {
        $this->totalRequests++;
        $this->failedRequests++;
        
        $errorMessage = $this->normalizeErrorMessage($reason);
        
        if (!isset($this->errors[$errorMessage])) {
            $this->errors[$errorMessage] = [
                'count' => 0
            ];
        }
        
        $this->errors[$errorMessage]['count']++;
    }
    
    public function getTimeStats(?int $code = null): array
    {
        if ($code !== null) {
            return $this->calculateTimeStats($this->statusCodes[$code]['times'] ?? []);
        }
        
        // Calculate overall time stats
        $allTimes = array_column($this->responseData, 'time');
        return $this->calculateTimeStats($allTimes);
    }
    
    public function getStatusDistribution(): array
    {
        $result = [];
        
        foreach ($this->statusCodes as $code => $data) {
            $count = $data['count'];
            $percentage = ($count / $this->totalRequests) * 100;
            
            $result[$code] = [
                'count' => $count,
                'percentage' => $percentage,
                'times' => $this->calculateTimeStats($data['times'])
            ];
        }
        
        return $result;
    }
    
    public function getErrors(): array
    {
        $result = [];
        
        foreach ($this->errors as $message => $data) {
            $count = $data['count'];
            $percentage = ($count / $this->totalRequests) * 100;
            
            $result[$message] = [
                'count' => $count,
                'percentage' => $percentage
            ];
        }
        
        return $result;
    }
    
    private function calculateTimeStats(array $times): array
    {
        if (empty($times)) {
            return [
                'min' => 0,
                'max' => 0,
                'avg' => 0,
                'total' => 0
            ];
        }
        
        return [
            'min' => min($times),
            'max' => max($times),
            'avg' => array_sum($times) / count($times),
            'total' => array_sum($times)
        ];
    }
    
    private function normalizeErrorMessage($reason): string
    {
        if (is_object($reason) && method_exists($reason, 'getMessage')) {
            return $reason->getMessage();
        }
        
        return (string) $reason;
    }
}