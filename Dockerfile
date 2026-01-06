# Use a imagem oficial do PHP
FROM php:8.4-cli

# Adicionar um usuário não privilegiado
RUN useradd -ms /bin/bash phpuser

# Definir um diretório de trabalho dentro do contêiner
WORKDIR /var/www/html

# Copiar o código PHP para dentro do contêiner e definir o proprietário como phpuser
COPY --chown=phpuser:phpuser . /var/www/html

# Expor a porta 8080 para acessar o PHP via HTTP
EXPOSE 8080

ENV K8S_API_URL="https://api.asdas.asdas.asdasda.com:6443"
ENV K8S_BEARER_TOKEN="sha256~GQnsSetBpUj_CdhcGOMgwasdasdasdasdcwE"
ENV K8S_SKIP_TLS_VERIFY=1  

# Alterar para o usuário não privilegiado antes de iniciar o servidor PHP
USER phpuser

# Executar o servidor PHP embutido quando o contêiner for iniciado
CMD ["php", "-S", "0.0.0.0:8080", "-t", "/var/www/html"]

