# Binpacking — Cluster (Live)

Visualização em tempo quase real dos pods por nó, consultando diretamente a API do Kubernetes e exibindo a ocupação por requests de CPU ou Memória.

## Variáveis de ambiente

- K8S_API_URL: URL base da API do cluster (ex.: https://10.0.0.1:6443)
- K8S_BEARER_TOKEN: Token Bearer para autenticação (ServiceAccount com permissão de ler nodes e pods)
- K8S_SKIP_TLS_VERIFY: Se definido (qualquer valor), desabilita a verificação de TLS (útil para clusters com certs self-signed)

## Como usar (exemplos)

Docker (imagem deste repositório):

```sh
# Exemplo — ajuste URL/TOKEN conforme seu cluster
export K8S_API_URL="https://10.0.0.1:6443"
export K8S_BEARER_TOKEN="$(cat /caminho/token.txt)"
export K8S_SKIP_TLS_VERIFY=1

docker build -t binpacking-k8s .

docker run --rm -e K8S_API_URL -e K8S_BEARER_TOKEN -e K8S_SKIP_TLS_VERIFY -p 8080:8080 binpacking-k8s
# Depois abra http://localhost:8080/binpacking-live/
```

## Notas

- A UI usa requests dos containers (se ausentes, contam como 0).
- CPU é mostrada em millicores (unidades de 100m) e Memória em MiB (unidades de 256Mi para escala visual).
- A aplicação é somente leitura e não aplica alterações no cluster.
