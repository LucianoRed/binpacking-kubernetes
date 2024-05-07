#!/bin/bash
## =================================================
## Calculando Total de Recursos Disponiveis (cpu)
## =================================================
TOTALCPU=0
for x in $(oc get nodes --selector=node-role.kubernetes.io/worker -o jsonpath='{.items[*].metadata.name}'); do
    TOTALNODE=$(oc describe node $x | grep -A1 "Capacity" | grep "cpu" | awk '{print $2}')
    TOTALCPU=$(echo "$TOTALCPU + $TOTALNODE" | bc)
done
TOTALCPU=`echo "$TOTALCPU * 1000" | bc`
echo "Total de capacidade de CPU dos nós workers: $TOTALCPU milicores"

## =================================================
## Calculando Total de Recursos Alocados (cpu)
## =================================================
TOTALCPUALOCADO=0
for x in $(oc get nodes --selector=node-role.kubernetes.io/worker -o jsonpath='{.items[*].metadata.name}'); do
    TOTALNODEALOCADO=`oc describe node ip-10-0-29-98.ec2.internal | grep -A4 "Allocated resources" | grep cpu | awk {'print $2'} | sed 's/m//'`;
    TOTALCPUALOCADO=$(echo "$TOTALCPUALOCADO + $TOTALNODEALOCADO" | bc)
done
echo "Total de capacidade de CPU Alocada nos nós workers: $TOTALCPUALOCADO milicores"

