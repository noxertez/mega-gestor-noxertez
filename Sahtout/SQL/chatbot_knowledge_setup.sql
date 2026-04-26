-- ============================================================
-- CHATBOT NOXERTEZ - Base de Conocimiento
-- ============================================================

CREATE TABLE IF NOT EXISTS `chatbot_preguntas` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `categoria`   VARCHAR(50)  NOT NULL DEFAULT 'General',
  `pregunta`    TEXT         NOT NULL,
  `respuesta`   TEXT         NOT NULL,
  `keywords`    TEXT         DEFAULT NULL COMMENT 'Palabras clave para búsqueda rápida',
  `updated_at`  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insertar Datos de la Base de Conocimiento
INSERT INTO `chatbot_preguntas` (`categoria`, `pregunta`, `respuesta`, `keywords`) VALUES
('PEDIDOS Y PLAZOS', '¿Cuánto tarda un pedido? / ¿Cuándo recibiré mi pedido? / ¿Qué plazo tenéis?', 'Nuestros pedidos estándar se preparan en 2-4 días hábiles. Si tu pieza lleva personalización (foto, nombre, color específico), puede tardar entre 4-7 días hábiles. Una vez enviado, el transportista entrega en 24-48h en península.', 'tarda,plazo,dias,recibir,cuando,llegada,tiempo'),
('PEDIDOS Y PLAZOS', '¿Puedo recibir mi pedido antes? / ¿Tenéis envío urgente?', 'Si necesitas la pieza con urgencia, cuéntanos por WhatsApp y miramos qué podemos hacer según la carga de trabajo del taller. Hacemos lo posible por ayudarte 🙂', 'urgente,antes,pronto,rapido,prioridad'),
('PEDIDOS Y PLAZOS', '¿Puedo cancelar o modificar mi pedido?', 'Si el pedido aún está en estado "Pendiente" podemos cancelarlo o modificarlo sin problema. Si ya está en producción, depende de la fase en que esté. Escríbenos por WhatsApp cuanto antes.', 'cancelar,modificar,cambiar,error'),
('PEDIDOS Y PLAZOS', '¿Cómo sé en qué estado está mi pedido?', 'Te avisamos por WhatsApp en cada cambio de estado: cuando empezamos a prepararlo, cuando está listo y cuando se envía. Si quieres una actualización en cualquier momento, pregúntanos directamente.', 'estado,como va,donde esta,seguimiento'),
('PEDIDOS Y PLAZOS', '¿Me avisáis cuando se envíe?', 'Sí, cuando tu pedido salga del taller te mandamos un mensaje de WhatsApp con el número de seguimiento para que puedas rastrearlo en tiempo real.', 'aviso,envio,seguimiento,tracking,numero'),

('ENVÍOS', '¿Hacéis envíos? / ¿Enviáis a toda España?', 'Sí, enviamos a toda la península española. Los envíos se gestionan a través de agencias de transporte con seguimiento incluido.', 'donde,españa,peninsula,haceis envios'),
('ENVÍOS', '¿Enviáis a Canarias, Baleares, Ceuta o Melilla?', 'Sí podemos enviar, aunque el coste y el plazo son diferentes. Escríbenos por WhatsApp con tu dirección y te calculamos el precio exacto.', 'canarias,baleares,ceuta,melilla,islas'),
('ENVÍOS', '¿Enviáis a otros países? / ¿Hacéis envíos internacionales?', 'De momento nos centramos en España, pero si estás interesado desde otro país escríbenos y lo valoramos. Para pedidos especiales siempre buscamos solución.', 'internacional,extranjero,fuera,francia,portugal,otro pais'),
('ENVÍOS', '¿Cuánto cuesta el envío?', 'El coste de envío depende del peso y destino. Te lo confirmamos siempre antes de cerrar el pedido. Para pedidos grandes o combinados intentamos optimizar al máximo.', 'coste,precio envio,gastos envio,gratis,cuanto vale'),
('ENVÍOS', '¿Con qué transportista enviáis?', 'Trabajamos con agencias de transporte gestionadas a través de Packlink, eligiendo siempre la opción más fiable para cada envío.', 'transportista,correos,seur,gls,mensajeria'),
('ENVÍOS', '¿Puedo recoger en persona?', 'Si estás cerca del taller podemos acordarlo. Escríbenos por WhatsApp y lo organizamos sin problema.', 'recoger,taller,tienda,mano,presencial'),

('PRODUCTOS Y PERSONALIZACIÓN', '¿Hacéis productos personalizados? / ¿Podéis poner un nombre o foto?', 'Sí, la personalización es una de nuestras especialidades. Podemos añadir nombres, fechas, fotos y dedicatorias en muchas de nuestras piezas. Cuéntanos qué tienes en mente por WhatsApp.', 'personalizado,nombre,foto,grabar,dedicatoria,especial'),
('PRODUCTOS Y PERSONALIZACIÓN', '¿En qué colores están disponibles las piezas?', 'La mayoría de nuestras piezas se pueden hacer en una amplia gama de colores: rojo, azul, verde, amarillo, naranja, morado, negro, aguamarina, celeste y más. Trabajamos bajo pedido de color para no tener stock muerto y asegurarnos de darte exactamente lo que buscas.', 'colores,rojo,azul,verde,personalizar color'),
('PRODUCTOS Y PERSONALIZACIÓN', '¿De qué material están hechas las piezas?', 'Todas nuestras piezas están hechas a mano en madera. Usamos diferentes tipos según el producto: madera de pino, madera noble, y maderas recicladas o de deriva para piezas especiales. Todo trabajo artesanal, sin producción en serie.', 'material,madera,pino,hecho de'),
('PRODUCTOS Y PERSONALIZACIÓN', '¿Hacéis piezas a medida? / ¿Podéis hacer algo que no está en el catálogo?', 'Sí, aceptamos encargos especiales. Si tienes una idea en mente, cuéntanosla por WhatsApp y valoramos si podemos hacerla. Nos gustan los retos creativos 🪵', 'medida,encargo,idea,nuevo,catalogo,especial'),
('PRODUCTOS Y PERSONALIZACIÓN', '¿Las piezas son hechas a mano?', 'Sí, absolutamente todas. Cada pieza sale de nuestro taller, trabajada y acabada a mano. No hay producción industrial ni intermediarios.', 'mano,artesano,taller,fabrica'),
('PRODUCTOS Y PERSONALIZACIÓN', '¿Tenéis catálogo completo?', 'Puedes ver nuestros productos en la web y también en Trendioff. Si buscas algo concreto dímelo y te digo si lo tenemos o podemos hacerlo.', 'catalogo,web,donde ver,trendioff'),
('PRODUCTOS Y PERSONALIZACIÓN', '¿Hacéis regalos de empresa / corporativos?', 'Sí, podemos hacer lotes personalizados con el logo o nombre de tu empresa. Para pedidos grandes escríbenos por WhatsApp y te preparamos presupuesto.', 'empresa,corporativo,lotes,logo,muchos,cantidad'),

('PRECIOS Y PAGOS', '¿Cómo se paga? / ¿Qué formas de pago aceptáis?', 'Aceptamos transferencia bancaria y pago a través de las plataformas donde vendemos (Trendioff, etc.). Para pedidos directos por WhatsApp te indicamos los datos al confirmar el pedido.', 'pagar,metodos,pago,tarjeta,transferencia,bizum'),
('PRECIOS Y PAGOS', '¿Hacéis descuentos? / ¿Tenéis ofertas?', 'De vez en cuando tenemos promociones especiales. Síguenos en Instagram o pregúntanos directamente, a veces hay sorpresas 😊', 'descuento,oferta,promocion,barato,rebaja'),
('PRECIOS Y PAGOS', '¿Puedo pagar a plazos?', 'Para pedidos grandes o corporativos podemos hablar de condiciones. Escríbenos y lo valoramos.', 'plazos,cuotas,financiar,meses'),
('PRECIOS Y PAGOS', '¿Los precios incluyen IVA?', 'Los precios que ves incluyen todos los impuestos aplicables. Sin sorpresas al final.', 'iva,impuestos,precio total'),

('DEVOLUCIONES Y GARANTÍAS', '¿Puedo devolver un producto? / ¿Cuál es vuestra política de devoluciones?', 'Si la pieza llega dañada o hay un error por nuestra parte, lo solucionamos sin coste para ti. Para piezas personalizadas no podemos aceptar devolución salvo defecto de fabricación, ya que se hacen específicamente para ti.', 'devolver,devolucion,garantia,error,fallo'),
('DEVOLUCIONES Y GARANTÍAS', '¿Qué pasa si llega roto o dañado?', 'Cuéntanoslo por WhatsApp con una foto y lo gestionamos inmediatamente. Nos hacemos cargo de los daños en el transporte.', 'roto,dañado,golpe,transporte,queja'),
('DEVOLUCIONES Y GARANTÍAS', '¿Tenéis garantía en los productos?', 'Garantizamos la calidad artesanal de todas nuestras piezas. Si detectas algún problema de fabricación después de recibirla, escríbenos y lo resolvemos.', 'garantia,calidad,problema,defecto'),

('SOBRE NOXERTEZ', '¿Quiénes sois? / ¿Qué es Noxertez?', 'Noxertez es un taller artesanal donde cada pieza se diseña y fabrica a mano. Trabajamos la madera para crear decoración única, regalos personalizados y piezas especiales. Detrás hay una sola persona con mucha pasión por el oficio 🪵', 'quienes,que es,historia,noxertez'),
('SOBRE NOXERTEZ', '¿Dónde estáis? / ¿Tenéis tienda física?', 'Somos un taller artesanal sin tienda física permanente. Vendemos online y ocasionalmente participamos en mercados y ferias. Síguenos para enterarte de cuándo estamos cerca de ti.', 'donde,tienda,fisica,ubicacion,donde estais'),
('SOBRE NOXERTEZ', '¿Tenéis redes sociales?', 'Sí, estamos en Instagram y TikTok. Puedes ver el proceso de fabricación, novedades y trabajos recientes. ¡Síguenos para no perderte nada!', 'instagram,tiktok,redes,sociales'),
('SOBRE NOXERTEZ', '¿Dónde puedo ver más productos? / ¿Estáis en alguna plataforma?', 'Además de esta web, puedes encontrarnos en Trendioff. Si quieres ver algo concreto pregúntame directamente.', 'trendioff,plataforma,vender'),

('PREGUNTAS RARAS O DIFÍCILES', '¿Sois una empresa grande?', 'No, somos un taller artesanal pequeño e independiente. Eso es precisamente lo que hace que cada pieza sea única y tenga alma. Tratas directamente con quien hace el producto.', 'grande,empresa,equipo'),
('PREGUNTAS RARAS O DIFÍCILES', '¿Usáis madera sostenible?', 'Trabajamos con maderas de procedencia responsable y en muchos casos reutilizamos y aprovechamos al máximo cada pieza de madera. El respeto por el material forma parte de nuestra filosofía.', 'sostenible,medio ambiente,reciclada,madera eco'),
('PREGUNTAS RARAS O DIFÍCILES', '¿Puedo encargar algo que he visto en Instagram?', 'Claro, si has visto algo que te ha gustado cuéntanoslo por WhatsApp con una captura o descripción y lo preparamos para ti.', 'instagram,encargo,visto,foto redes'),
('PREGUNTAS RARAS O DIFÍCILES', 'No encuentro lo que busco', 'Cuéntame qué estás buscando exactamente y miro si lo tenemos o podemos hacerlo. Si no está en el catálogo, a veces se puede hacer igualmente 😊', 'buscar,no encuentro,falta'),
('PREGUNTAS RARAS O DIFÍCILES', 'Quiero hacer un pedido / Me interesa comprar', '¡Perfecto! La forma más rápida es escribirnos por WhatsApp directamente. Te atendemos personalmente y cerramos todos los detalles contigo. 👇 [botón WhatsApp]', 'pedido,comprar,hacer pedido,comprar ahora,interesa')
ON DUPLICATE KEY UPDATE `respuesta` = VALUES(`respuesta`);
