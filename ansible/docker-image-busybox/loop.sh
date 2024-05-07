#!/bin/sh

# Defina a precisão com base na variável de ambiente, ou use um valor padrão
SCALE=${SCALE:-500}

while true; do
    # Execute uma operação que consuma CPU em nível médio
    # Por exemplo, calculando a raiz quadrada repetidamente
    result=$(echo "scale=$SCALE; 4*a(1)" | bc -l)
    echo "Resultado da iteração: $result"
    sleep 1  # Aguarda 1 segundo entre as iterações
done

