<?php

namespace App\Services\Agent;

use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\Lead;
use App\Services\Knowledge\RetrievalResult;

/**
 * The outcome of one turn, including why it went that way.
 */
class AgentTurn
{
    /**
     * @param  array<string, mixed>  $extracted
     */
    public function __construct(
        public readonly string $reply,
        public readonly Lead $lead,
        public readonly Conversation $conversation,
        public readonly RetrievalResult $knowledge,
        public readonly array $extracted,
        public readonly ConversationMessage $message,
    ) {}
}
