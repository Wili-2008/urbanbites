<?php
class AuthenticationException extends RuntimeException
{
}

class AuthorizationException extends RuntimeException
{
}

abstract class ApiController
{
    protected function input()
    {
        if (isset($GLOBALS['_API_BODY']) && is_array($GLOBALS['_API_BODY'])) {
            return $GLOBALS['_API_BODY'];
        }
        if (stripos($_SERVER['CONTENT_TYPE'] ?? '', 'multipart/form-data') === 0) {
            return $_POST;
        }
        $raw = file_get_contents('php://input');
        if ($raw === '' || $raw === false) {
            return $_POST;
        }
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            throw new InvalidArgumentException('El cuerpo debe contener un objeto JSON válido.');
        }
        return $data;
    }

    protected function respond($data, $status = 200)
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    protected function run(callable $operation, $successStatus = 200)
    {
        try {
            $this->respond($operation(), $successStatus);
        } catch (InvalidArgumentException $exception) {
            $this->respond(['error' => $exception->getMessage()], 400);
        } catch (DomainException $exception) {
            $this->respond(['error' => $exception->getMessage()], 409);
        } catch (AuthenticationException $exception) {
            $this->respond(['error' => $exception->getMessage()], 401);
        } catch (AuthorizationException $exception) {
            $this->respond(['error' => $exception->getMessage()], 403);
        } catch (PDOException $exception) {
            error_log($exception->getMessage());
            $status = $exception->getCode() === '23000' ? 409 : 500;
            $this->respond(['error' => $status === 409 ? 'La operación infringe una restricción de datos.' : 'Error de base de datos.'], $status);
        } catch (Throwable $exception) {
            error_log($exception->getMessage());
            $this->respond(['error' => 'Error interno del servidor.'], 500);
        }
    }

    protected function requireFields(array $data, array $fields)
    {
        foreach ($fields as $field) {
            if (!array_key_exists($field, $data)) {
                throw new InvalidArgumentException("Falta el campo obligatorio: {$field}.");
            }
        }
    }

    protected function positiveInteger($value, $field)
    {
        $integer = filter_var($value, FILTER_VALIDATE_INT);
        if ($integer === false || $integer < 1) {
            throw new InvalidArgumentException("El campo {$field} debe ser un entero positivo.");
        }
        return $integer;
    }
}
