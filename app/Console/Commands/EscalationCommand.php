<?php

namespace App\Console\Commands;

use App\BusinessLogic\Escalation\ClaimRejectEscalation;
use App\BusinessLogic\Escalation\DefaultOnPayment;
use App\BusinessLogic\Escalation\PaymentTime;
use App\BusinessLogic\Escalation\StatusRequest;
use App\BusinessLogic\Escalation\EscalationSendMail;
use Illuminate\Console\Command;

class EscalationCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:escalation-command';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        //

        (new ClaimRejectEscalation)->send_escalation();
        (new PaymentTime)->send_escalation();
        (new DefaultOnPayment)->send_escalation();
        (new StatusRequest)->send_escalation();
        (new EscalationSendMail)->send_escalation_email();
    }
}
