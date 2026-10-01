import { useEffect } from 'react';
import { useRouteError } from 'react-router';
import { MessagePage } from './NotFoundPage';

/**
 * Error inesperado de renderizado o de carga de un modulo. Se registra en la
 * consola para depurar, pero a la persona solo se le muestra un mensaje claro.
 */
export function RouteError() {
  const error = useRouteError();

  useEffect(() => {
    console.error(error);
  }, [error]);

  const chunkFailed = error instanceof Error && /dynamically imported module|Loading chunk/i.test(error.message);

  return (
    <MessagePage
      code="Ups"
      title={chunkFailed ? 'Hay una versión nueva disponible' : 'Algo no salió como esperábamos'}
      text={
        chunkFailed
          ? 'Recarga la página para continuar con la versión más reciente.'
          : 'No perdiste nada de lo que ya estaba guardado. Recarga la página o vuelve al inicio para intentarlo de nuevo.'
      }
    />
  );
}
