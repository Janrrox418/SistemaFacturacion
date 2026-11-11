<?php

final class SapApiException extends RuntimeException
{
    public function __construct(
        string $message,
        private ?int $httpStatusCode = null,
        private string $responseBody = ''
    ) {
        parent::__construct($message);
    }

    public function getHttpStatusCode(): ?int
    {
        return $this->httpStatusCode;
    }

    public function getResponseBody(): string
    {
        return $this->responseBody;
    }
}

final class SapApiService
{
    public function __construct(
        private string $baseUrl,
        private ?string $username = null,
        private ?string $password = null,
        private int $timeout = 90
    ) {
        $scheme = parse_url($baseUrl, PHP_URL_SCHEME);
        if (!filter_var($baseUrl, FILTER_VALIDATE_URL) || !in_array($scheme, ['http', 'https'], true)) {
            throw new InvalidArgumentException('La URL de SAP debe ser una URL HTTP o HTTPS válida.');
        }

        if (($username === null) !== ($password === null)) {
            throw new InvalidArgumentException('Se deben proporcionar ambos datos de autenticación de SAP.');
        }

        if ($timeout < 1) {
            throw new InvalidArgumentException('El tiempo de espera debe ser mayor que cero.');
        }
    }

    public function get(array $query = []): mixed
    {
        return $this->request('GET', $query);
    }

    public function post(array $payload, array $query = []): mixed
    {
        return $this->request('POST', $query, $payload);
    }

    public function patch(array $payload, array $query = []): mixed
    {
        return $this->request('PATCH', $query, $payload);
    }

    public function put(array $payload, array $query = []): mixed
    {
        return $this->request('PUT', $query, $payload);
    }

    public function putRaw(array $payload, array $query = []): string
    {
        return $this->request('PUT', $query, $payload, true);
    }

    public function patchQuery(array $query): mixed
    {
        return $this->request('PATCH', $query);
    }

    private function request(
        string $method,
        array $query,
        ?array $payload = null,
        bool $rawResponse = false
    ): mixed {
        $executionLimit = max(60, $this->timeout + 30);
        if (function_exists('set_time_limit')) {
            set_time_limit($executionLimit);
        }

        $url = $this->buildUrl($query);
        $curl = curl_init($url);
        if ($curl === false) {
            throw new RuntimeException('No se pudo iniciar la solicitud a SAP.');
        }

        $headers = ['Accept: application/json'];
        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
        ];

        if ($this->username !== null && $this->password !== null) {
            $options[CURLOPT_HTTPAUTH] = CURLAUTH_BASIC;
            $options[CURLOPT_USERPWD] = $this->username . ':' . $this->password;
        }

        if ($payload !== null) {
            $options[CURLOPT_HTTPHEADER][] = 'Content-Type: application/json';
            $options[CURLOPT_POSTFIELDS] = json_encode($payload, JSON_THROW_ON_ERROR);
        }

        if (!curl_setopt_array($curl, $options)) {
            $curlError = curl_error($curl);
            throw new RuntimeException('No se pudieron configurar las opciones de la solicitud SAP: ' . $curlError);
        }

        $response = curl_exec($curl);
        $httpStatusCode = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $curlError = curl_error($curl);
        $curlErrorNumber = curl_errno($curl);

        if ($response === false) {
            throw new RuntimeException(sprintf(
                'Falló la solicitud a SAP (cURL %d, HTTP %d): %s',
                $curlErrorNumber,
                $httpStatusCode,
                $curlError
            ));
        }

        if ($httpStatusCode < 200 || $httpStatusCode >= 300) {
            throw new SapApiException(
                'SAP devolvió un error HTTP ' . $httpStatusCode . '.',
                $httpStatusCode,
                $response
            );
        }

        if ($response === '') {
            return $rawResponse ? '' : null;
        }

        return $rawResponse
            ? $response
            : json_decode($response, true, 512, JSON_THROW_ON_ERROR);
    }

    private function buildUrl(array $query): string
    {
        if ($query === []) {
            return $this->baseUrl;
        }

        $url = $this->baseUrl;
        $fragment = '';
        $fragmentPosition = strpos($url, '#');
        if ($fragmentPosition !== false) {
            $fragment = substr($url, $fragmentPosition);
            $url = substr($url, 0, $fragmentPosition);
        }

        $queryPosition = strpos($url, '?');
        if ($queryPosition === false) {
            $baseUrl = $url;
            $queryString = '';
        } else {
            $baseUrl = substr($url, 0, $queryPosition);
            $queryString = substr($url, $queryPosition + 1);
        }
        parse_str($queryString, $existingQuery);
        $queryString = http_build_query(array_merge($existingQuery, $query));

        return $baseUrl . '?' . $queryString . $fragment;
    }
}
