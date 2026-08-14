<?php
declare(strict_types=1);

namespace Bambamboole\Spectacular\ModelStates;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

final class StateTransitionDenied extends RuntimeException
{
    /**
     * @param  list<string>  $allowedStates
     */
    private function __construct(
        string $message,
        public readonly string $currentState,
        public readonly string $requestedState,
        public readonly array $allowedStates,
    ) {
        parent::__construct($message);
    }

    /**
     * @param  list<string>  $allowedStates
     */
    public static function for(Model $model, string $from, string $to, array $allowedStates): self
    {
        return new self(
            __('spectacular::states.transition-denied', [
                'model' => (string) str(class_basename($model))->headline()->lower(),
                'from' => $from,
                'to' => $to,
            ]),
            currentState: $from,
            requestedState: $to,
            allowedStates: $allowedStates,
        );
    }

    public function render(Request $request): JsonResponse
    {
        return new JsonResponse([
            'message' => $this->getMessage(),
            'current_state' => $this->currentState,
            'requested_state' => $this->requestedState,
            'allowed_states' => $this->allowedStates,
        ], JsonResponse::HTTP_CONFLICT);
    }
}
