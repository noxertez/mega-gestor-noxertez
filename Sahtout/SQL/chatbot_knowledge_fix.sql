SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

TRUNCATE TABLE chatbot_preguntas;

INSERT INTO chatbot_preguntas (categoria, pregunta, respuesta, keywords) VALUES
('PEDIDOS Y PLAZOS', '¿Cuánto tarda un pedido? / ¿Cuándo recibiré mi pedido? / ¿Qué plazo tenéis?', 'Nuestros pedidos estándar se preparan en 2-4 días hábiles. Si tu pieza lleva personalización (foto, nombre, color específico), puede tardar entre 4-7 días hábiles. Una vez enviado, el transportista entrega en 24-48h en península.', 'tarda,plazo,dias,recibir,cuando,llegada,tiempo'),
('PEDIDOS Y PLAZOS', '¿Puedo recibir mi pedido antes? / ¿Tenéis envío urgente?', 'Si necesitas la pieza con urgencia, cuéntanos por WhatsApp y miramos qué podemos hacer según la carga de trabajo del taller. Hacemos lo posible por ayudarte 🙂', 'urgente,antes,pronto,rapido,prioridad'),
('PEDIDOS Y PLAZOS', '¿Puedo cancelar o modificar mi pedido?', 'Si el pedido aún está en estado "Pendiente" podemos cancelarlo o modificarlo sin problema. Si ya está en producción, depende de la fase en que esté. Escríbenos por WhatsApp cuanto antes.', 'cancelar,modificar,cambiar,error,arrepentido'),
('PEDIDOS Y PLAZOS', '¿Cómo sé en qué estado está mi pedido?', 'Recibirás correos automáticos: 1. Confirmación de pedido. 2. Cuando esté en preparación. 3. Cuando haya sido enviado (con el link de seguimiento de la agencia).', 'estado,seguimiento,donde,donde esta,tracking'),
('PEDIDOS Y PLAZOS', '¿Me avisáis cuando se envíe?', '¡Sí! En cuanto el transportista recoja tu paquete, te llegará un email con el número de seguimiento para que sepas exactamente cuándo llega.', 'aviso,envio,correo,notificacion,avisar'),

('ENVÍOS Y ENTREGAS', '¿A dónde hacéis envíos?', 'Realizamos envíos a toda la Península y Baleares. Si estás en Canarias, Ceuta, Melilla o fuera de España, consúltanos por WhatsApp para calcular los costes de envío específicos.', 'donde,canarias,baleares,extranjero,envian'),
('ENVÍOS Y ENTREGAS', '¿Qué empresa de transporte utilizáis?', 'Trabajamos principalmente con GLS, Correos Express y Tipsa, dependiendo de la zona y el volumen del paquete, para asegurar que llegue lo más rápido posible.', 'transporte,agencia,gls,correos,tipsa,paquete'),
('ENVÍOS Y ENTREGAS', '¿Qué pasa si no estoy en casa en el momento de la entrega?', 'No te preocupes. El transportista te dejará un aviso o te contactará por teléfono para concertar una segunda entrega o indicarte un punto de recogida cercano.', 'casa,ausente,entrega,recoger,aviso'),
('ENVÍOS Y ENTREGAS', 'Mi pedido aparece como "entregado" pero no lo tengo', 'A veces lo dejan a un vecino o en un comercio cercano si no estabas. Si no lo localizas, escríbenos por WhatsApp y nosotros abriremos una incidencia con la agencia.', 'perdido,no ha llegado,incidencia,problema'),

('PRODUCTOS Y MADERA', '¿La madera está tratada?', 'Sí, todas nuestras piezas reciben un tratamiento protector con aceites naturales o ceras que realzan la veta y protegen la madera, manteniendo su tacto natural.', 'tratada,madera,barniz,aceite,proteccion'),
('PRODUCTOS Y MADERA', '¿Son piezas únicas?', 'Totalmente. Al trabajar con madera recuperada y troncos naturales, no hay dos piezas iguales. La veta, los nudos y los matices del color hacen que tu pieza sea exclusiva.', 'unica,igual,diferente,exclusiva,artesania'),
('PRODUCTOS Y MADERA', '¿Cómo debo cuidar mi pieza de madera?', 'Evita la exposición directa al sol prolongada y fuentes de calor intenso (radiadores). Para limpiarla, basta con un paño seco o ligeramente húmedo. No uses productos químicos agresivos.', 'cuidado,limpiar,sol,mantenimiento,conservar'),
('PRODUCTOS Y MADERA', '¿Hacéis muebles a medida?', 'Estamos enfocados en decoración y piezas de pequeño/mediano formato, pero si tienes una idea especial de algo más grande, ¡cuéntanosla por WhatsApp! Nos encantan los retos.', 'medida,muebles,especial,encargo,personalizado'),

('PAGOS Y SEGURIDAD', '¿Qué métodos de pago aceptáis?', 'Puedes pagar de forma segura con Tarjeta de Crédito/Débito, PayPal y Bizum. Todos los pagos están encriptados y son 100% seguros.', 'pago,tarjeta,paypal,bizum,pagar'),
('PAGOS Y SEGURIDAD', '¿Es seguro comprar en vuestra web?', 'Absolutamente. Nuestra web cuenta con certificado SSL (el candado verde) y todas las transacciones se realizan a través de pasarelas de pago oficiales y seguras.', 'seguro,seguridad,estafa,confianza,datos'),
('PAGOS Y SEGURIDAD', '¿Puedo pedir factura de mi compra?', '¡Claro! Si necesitas factura, escríbenos por WhatsApp o email con tus datos fiscales y te la enviaremos en formato PDF.', 'factura,iva,autonomo,empresa,recibo'),

('DEVOLUCIONES', '¿Puedo devolver un producto?', 'Tienes 14 días naturales para devolver piezas estándar si no quedas satisfecho. El producto debe estar en el mismo estado en que se recibió.', 'devolver,devolucion,garantia,arrepentido'),
('DEVOLUCIONES', '¿Los productos personalizados tienen devolución?', 'Las piezas personalizadas (con nombres, fotos o grabados específicos) no admiten devolución según la ley de comercio, a menos que tengan un defecto de fabricación.', 'devolucion,personalizado,nombre,grabado,cambio'),
('DEVOLUCIONES', '¿Qué hago si mi pedido llega roto o dañado?', '¡Escríbenos por WhatsApp al momento! Envíanos una foto del paquete y del daño. Te enviaremos una pieza nueva sin coste adicional para ti. Tu satisfacción es lo primero.', 'roto,dañado,golpe,roto en envio,sustitucion'),

('SOBRE NOXERTEZ', '¿Dónde estáis ubicados?', 'Nuestro taller está en España. No tenemos tienda física abierta al público habitual, ya que nos centramos en la venta online y en el trabajo artesanal en el taller.', 'donde estan,taller,ubicacion,tienda,donde estais'),
('SOBRE NOXERTEZ', '¿De dónde sacáis la madera?', 'Utilizamos principalmente madera recuperada de podas controladas y bosques sostenibles. Nos apasiona dar una segunda vida a la madera que otros desechan.', 'madera,procedencia,sostenible,ecologico,recuperada'),
('SOBRE NOXERTEZ', '¿Quién hace las piezas?', 'Todo lo que ves está hecho a mano por nosotros en nuestro pequeño taller familiar. Ponemos mucho cariño y atención en cada detalle.', 'quien,nosotros,taller,familia,artesanos'),

('PREGUNTAS FRECUENTES (FAQ)', '¿Hacéis descuentos por cantidad?', 'Para eventos (bodas, bautizos, regalos de empresa) solemos aplicar tarifas especiales. Pregúntanos por WhatsApp el volumen que necesitas.', 'descuento,cantidad,boda,evento,empresa,regalo'),
('PREGUNTAS FRECUENTES (FAQ)', '¿Tenéis tarjetas regalo?', '¡Próximamente! De momento, si quieres regalar algo pero no te decides, escríbenos y te ayudamos a elegir la pieza perfecta.', 'regalo,tarjeta regalo,vale,vale regalo'),
('PREGUNTAS FRECUENTES (FAQ)', '¿Cómo puedo contactar con vosotros?', 'La forma más rápida es a través de nuestro botón de WhatsApp en la web. También puedes escribirnos por Instagram o al email de contacto.', 'contacto,hablar,ayuda,whatsapp,email');
