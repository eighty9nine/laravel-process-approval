<?php

namespace RingleSoft\LaravelProcessApproval\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use RingleSoft\LaravelProcessApproval\Facades\ProcessApproval;
use function Laravel\Prompts\alert;
use function Laravel\Prompts\info;
use function Laravel\Prompts\select;
use function Laravel\Prompts\table;
use function Laravel\Prompts\text;

class FlowCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'process-approval:flow {action} {params?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new Approval Flow';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        switch ($this->argument('action')) {
            case 'add':
                $model = $this->argument('params')
                    ??
                    $model = text("Enter the name of the model you want to make approvable", 'ModelName');
                $this->addFlow($model);
                break;
            case 'remove':
                $flowsArray = ProcessApproval::flows()
                    ->pluck('name', "id")
                    ->toArray();
                $choice = select('What Flow do you want to remove?', $flowsArray);
                $this->removeFlow($choice);
                break;
            case 'list':
                $this->listFlows($this->arguments());
                break;
            default:
                print('Unknown action ' . $this->argument('action'));
        }
    }

    /**
     * Create a new approval flow
     * @param $name
     * @return true
     */
    private function addFlow($name): bool
    {
        if (!Str::contains($name, '\\')) {
            $name = config('process_approval.models_path') . "\\{$name}";
        }
        if (class_exists($name)) {
            try {
                ProcessApproval::createFlow(
                    name: Str::of($name)->afterLast('\\')->snake(' ')->title()->toString(),
                    modelClass: get_class(new $name())
                );
                info("{$name} created successfully!");
            } catch (\Exception $e) {
                echo "Failed to create Flow: " . $e->getMessage();
            }
        } else {
            echo "The model `{$name}` you specified doesn't exist";
        }
        return true;
    }

    /**
     * Remove approval flow
     * @param $flow
     * @return bool
     */
    public function removeFlow($flow): bool
    {
        try {
            ProcessApproval::deleteFlow($flow);
            info("{$flow} removed successfully!");
        } catch (\Exception $e) {
            alert("Failed to delete flow. ". $e->getMessage());
            return false;
        }
        return true;
    }


    /**
     * @param $args
     * @return void
     */
    public function listFlows($args = null)
    {
        $flows = ProcessApproval::flows();
        $items = [];
        foreach ($flows as $index => $flow) {
            if (count($flow->steps) > 0) {
                foreach ($flow->steps as $index2 => $step) {
                    $items[] = [
                        $flow->name,
                        $step->role->name,
                        $step->action,
                        $step->active ? 'True' : 'False'
                    ];
                }
            } else {
                $items[] = [
                    $flow->name,
                    '--',
                    '--',
                    '--',
                ];
            }
        }
        $headers = ['Flow', 'Step (Role)', 'Action', 'Active'];
        table($headers, $items);
    }
}
