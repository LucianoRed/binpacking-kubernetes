# Simulação de carga com Ansible

Este diretório contém um playbook Ansible que gera manifests de Deployments (BestEffort, Burstable, Guaranteed) e constrói uma imagem local que simula carga com comportamento randômico (pod pode usar mais/menos CPU que seu request/limit).

O worker é um script Python em `docker-image-busybox/worker.py`. Os templates Jinja definem variáveis de ambiente `REQUEST_M` e `LIMIT_M` para que o container saiba seu request/limit em millicores.

Pré-requisitos
- Docker instalado e acessível pelo usuário
- (Opcional) Ansible >= 2.10 e a coleção `community.docker` se quiser usar o módulo Ansible para construir imagens
- kubectl configurado para o cluster alvo (minikube/Kind/cluster real)

Passos rápidos

1) (opção rápida) Build da imagem manualmente com Docker:

```bash
cd ansible/docker-image-busybox
docker build -t local/busybox-worker:latest .
```

2) Gerar os manifests (playbook Ansible):

- Usando Ansible (recomendado quando instalado):

```bash
ansible-playbook ansible/cria_deployments.yaml
```

- Ou, se não tiver Ansible, você pode apenas copiar os templates e ajustá-los manualmente; os manifests gerados irão para `ansible/deploy/`.

3) Aplicar os manifests no cluster:

```bash
kubectl apply -f ansible/deploy/
```

O que a imagem faz
- O worker (`worker.py`) executa um loop de trabalho de CPU com variação randômica (entre 0.2x e 1.8x) do `LIMIT_M` (ou do `REQUEST_M` caso não haja limit), gerando cargas que às vezes ficam abaixo do request, às vezes acima do limit.

Dicas e notas
- Se quiser controlar repetibilidade, passe valores fixos de `REQUEST_M`/`LIMIT_M` alterando os templates ou rodando `kubectl set env` nos deployments após aplicá-los.
- O playbook tenta usar `community.docker.docker_image` para construir a imagem; caso o módulo não esteja disponível ele possui um fallback que executa `docker build` via `command`.
- Ajuste o número de deployments/replicas em `ansible/cria_deployments.yaml`.

Próximos passos possíveis
- Adicionar um task Ansible para aplicar automaticamente os manifests (`kubectl apply`) após gerar os YAMLs.
- Adicionar um controller simples que recolhe métricas (por exemplo, via `kubectl top`) e gera gráficos.
- Tornar a simulação determinística via `seed`.

Se quiser, eu:
- Adiciono o passo automático de aplicar os manifests com Ansible (kubectl) e/ou criar um `ansible/inventory` para controlar destino, ou
- Faço a execução aqui (se você quiser que eu rode os comandos e reporte a saída) — diga qual opção prefere.
