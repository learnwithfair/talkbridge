<?php
namespace RahatulRabbi\TalkBridge\Traits;

trait ApiResponse
{
    public function success($data, string $message = null, int $code = 200, bool $pagination = false)
    {
        $response = ['success' => true, 'message' => $message, 'data' => $data, 'code' => $code];

        if ($pagination && $data !== null) {
            $response['meta'] = [
                'current_page'  => $data->currentPage(),
                'last_page'     => $data->lastPage(),
                'per_page'      => $data->perPage(),
                'total'         => $data->total(),
                'prev_page_url' => $data->previousPageUrl(),
                'next_page_url' => $data->nextPageUrl(),
            ];
        }

        return response()->json($response, $code);
    }

    public function error($data, string $message = null, int $code = 500)
    {
        return response()->json([
            'success' => false, 'message' => $message, 'data' => $data, 'code' => $code,
        ], $code);
    }
}
