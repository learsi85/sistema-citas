import React, { useState, useEffect } from 'react';
import { Calendar, Clock, Users, Briefcase, Settings, Plus, Trash2, Edit, AlertCircle, Check, Contact, Eye, ChevronLeft, ChevronRight, EyeClosed } from 'lucide-react';
import './App.css';

//const API_URL = 'http://localhost/sistema-citas/backend/api';
const API_URL = 'https://acciontic.com.mx/sistema-citas/api';

const X = ({className = "w-6 h-6"}) => <svg className={className} fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" /></svg>;

const AdminPanel = () => {
  const [activeTab, setActiveTab] = useState('empresa');
  const [empresa, setEmpresa] = useState({});
  const [servicios, setServicios] = useState([]);
  const [disponibilidad, setDisponibilidad] = useState([]);
  const [citas, setCitas] = useState([]);
  const [clientes, setClientes] = useState([]);
  const [proveedores, setProveedores] = useState([]);
  const [alerta, setAlerta] = useState(null);
  const [loading, setLoading] = useState(false);

  useEffect(() => {
    cargarDatos();
    if (activeTab === 'citas') {
      cargarServicios();
    }
  }, [activeTab]);

  const cargarDatos = async () => {
    try {
      if (activeTab === 'empresa') {
        const res = await fetch(`${API_URL}/empresa.php`);
        const data = await res.json();
        setEmpresa(data || {});
      } else if (activeTab === 'servicios') {
        const res = await fetch(`${API_URL}/servicios.php`);
        setServicios(await res.json());
      } else if (activeTab === 'disponibilidad') {
        const res = await fetch(`${API_URL}/disponibilidad.php`);
        setDisponibilidad(await res.json());
      } else if (activeTab === 'proveedores') {
        const res = await fetch(`${API_URL}/proveedores.php`);
        setProveedores(await res.json());
        const resServicios = await fetch(`${API_URL}/servicios.php`);
        setServicios(await resServicios.json());
      } else if (activeTab === 'citas') {
        const res = await fetch(`${API_URL}/citas.php`);
        setCitas(await res.json());
        // Cargar servicios y clientes también para el formulario
        const resServicios = await fetch(`${API_URL}/servicios.php`);
        setServicios(await resServicios.json());
        const resClientes = await fetch(`${API_URL}/clientes.php`);
        setClientes(await resClientes.json());
        const resProveedor = await fetch(`${API_URL}/proveedores.php`);
        setProveedores(await resProveedor.json());
      }
    } catch (error) {
      mostrarAlerta('Error al cargar datos', 'error');
    }
  };

  const cargarServicios = async () => {
    try {
      const res = await fetch(`${API_URL}/servicios.php`);
      setServicios(await res.json());
      //console.log(servicios);
    } catch (error) {
      console.error('Error al cargar servicios:', error);
    }
  };

  const mostrarAlerta = (mensaje, tipo = 'success') => {
    setAlerta({ mensaje, tipo });
    setTimeout(() => setAlerta(null), 3000);
  };

  // ========== EMPRESA ==========
  const EmpresaForm = () => {
    const [form, setForm] = useState(empresa);

    useEffect(() => {
      setForm(empresa);
    }, [empresa]);

    const handleSubmit = async () => {
      try {
        await fetch(`${API_URL}/empresa.php`, {
          method: 'PUT',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(form)
        });
        mostrarAlerta('Datos de empresa actualizados');
        setEmpresa(form);
      } catch (error) {
        mostrarAlerta('Error al guardar', 'error');
      }
    };

    return (
      <div className="bg-white rounded-lg shadow p-6">
        <h2 className="text-2xl font-bold mb-6 flex items-center gap-2">
          <Briefcase className="w-6 h-6" />
          Datos de la Empresa
        </h2>
        <div className="space-y-4">
          <div>
            <label className="block text-sm font-medium mb-1">Nombre de la Empresa</label>
            <input
              type="text"
              value={form.nombre || ''}
              onChange={(e) => setForm({...form, nombre: e.target.value})}
              className="w-full px-3 py-2 border rounded-lg"
            />
          </div>
          <div>
            <label className="block text-sm font-medium mb-1">Dirección</label>
            <input
              type="text"
              value={form.direccion || ''}
              onChange={(e) => setForm({...form, direccion: e.target.value})}
              className="w-full px-3 py-2 border rounded-lg"
            />
          </div>
          <div className="grid grid-cols-2 gap-4">
            <div>
              <label className="block text-sm font-medium mb-1">Teléfono</label>
              <input
                type="tel"
                value={form.telefono || ''}
                onChange={(e) => setForm({...form, telefono: e.target.value})}
                className="w-full px-3 py-2 border rounded-lg"
              />
            </div>
            <div>
              <label className="block text-sm font-medium mb-1">Email</label>
              <input
                type="email"
                value={form.email || ''}
                onChange={(e) => setForm({...form, email: e.target.value})}
                className="w-full px-3 py-2 border rounded-lg"
              />
            </div>
          </div>
          <div>
            <label className="block text-sm font-medium mb-1">Descripción</label>
            <textarea
              value={form.descripcion || ''}
              onChange={(e) => setForm({...form, descripcion: e.target.value})}
              className="w-full px-3 py-2 border rounded-lg"
              rows="3"
            />
          </div>
          <button onClick={handleSubmit} className="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700">
            Guardar Cambios
          </button>
        </div>
      </div>
    );
  };

  // ========== SERVICIOS ==========
  const ServiciosTab = () => {
    const [showForm, setShowForm] = useState(false);
    const [editando, setEditando] = useState(null);
    const [form, setForm] = useState({
      clave: '', nombre: '', descripcion: '', precio: '', max_integrantes: ''
    });

    const resetForm = () => {
      setForm({ clave: '', nombre: '', descripcion: '', precio: '', max_integrantes: '' });
      setEditando(null);
      setShowForm(false);
    };

    const handleSubmit = async () => {
      try {
        const method = editando ? 'PUT' : 'POST';
        const body = editando ? {...form, id: editando} : form;
        
        await fetch(`${API_URL}/servicios.php`, {
          method,
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(body)
        });
        
        mostrarAlerta(editando ? 'Servicio actualizado' : 'Servicio creado');
        resetForm();
        cargarDatos();
      } catch (error) {
        mostrarAlerta('Error al guardar servicio', 'error');
      }
    };

    const eliminar = async (id) => {
      if (confirm('¿Eliminar este servicio?')) {
        await fetch(`${API_URL}/servicios.php?id=${id}`, { method: 'DELETE' });
        mostrarAlerta('Servicio eliminado');
        cargarDatos();
      }
    };

    const editar = (servicio) => {
      setForm(servicio);
      setEditando(servicio.id);
      setShowForm(true);
    };

    return (
      <div className="space-y-4">
        <div className="bg-white rounded-lg shadow p-6">
          <div className="flex justify-between items-center mb-6">
            <h2 className="text-2xl font-bold flex items-center gap-2">
              <Settings className="w-6 h-6" />
              Servicios
            </h2>
            <button
              onClick={() => setShowForm(!showForm)}
              className="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 flex items-center gap-2"
            >
              <Plus className="w-4 h-4" />
              Nuevo Servicio
            </button>
          </div>

          {showForm && (
            <div className="mb-6 p-4 bg-gray-50 rounded-lg space-y-3">
              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="block text-sm font-medium mb-1">Clave</label>
                  <input
                    type="text"
                    value={form.clave}
                    onChange={(e) => setForm({...form, clave: e.target.value})}
                    className="w-full px-3 py-2 border rounded"
                  />
                </div>
                <div>
                  <label className="block text-sm font-medium mb-1">Nombre</label>
                  <input
                    type="text"
                    value={form.nombre}
                    onChange={(e) => setForm({...form, nombre: e.target.value})}
                    className="w-full px-3 py-2 border rounded"
                  />
                </div>
              </div>
              <div>
                <label className="block text-sm font-medium mb-1">Descripción</label>
                <textarea
                  value={form.descripcion}
                  onChange={(e) => setForm({...form, descripcion: e.target.value})}
                  className="w-full px-3 py-2 border rounded"
                  rows="2"
                />
              </div>
              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="block text-sm font-medium mb-1">Precio</label>
                  <input
                    type="number"
                    step="0.01"
                    value={form.precio}
                    onChange={(e) => setForm({...form, precio: e.target.value})}
                    className="w-full px-3 py-2 border rounded"
                  />
                </div>
                <div>
                  <label className="block text-sm font-medium mb-1">Máx. Integrantes</label>
                  <input
                    type="number"
                    value={form.max_integrantes}
                    onChange={(e) => setForm({...form, max_integrantes: e.target.value})}
                    className="w-full px-3 py-2 border rounded"
                  />
                </div>
              </div>
              <div className="flex gap-2">
                <button onClick={handleSubmit} className="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
                  {editando ? 'Actualizar' : 'Crear'}
                </button>
                <button onClick={resetForm} className="bg-gray-300 px-4 py-2 rounded hover:bg-gray-400">
                  Cancelar
                </button>
              </div>
            </div>
          )}

          <div className="space-y-3">
            {servicios.map(servicio => (
              <div key={servicio.id} className="border rounded-lg p-4 flex justify-between items-center">
                <div>
                  <div className="font-semibold">{servicio.nombre} <span className="text-sm text-gray-500">({servicio.clave})</span></div>
                  <div className="text-sm text-gray-600">{servicio.descripcion}</div>
                  <div className="text-sm mt-1">
                    <span className="font-medium">${servicio.precio}</span> • 
                    <span className="ml-2"><Users className="inline w-4 h-4" /> {servicio.max_integrantes} personas</span>
                  </div>
                </div>
                <div className="flex gap-2">
                  <button onClick={() => editar(servicio)} className="text-blue-600 hover:text-blue-800">
                    <Edit className="w-5 h-5" />
                  </button>
                  <button onClick={() => eliminar(servicio.id)} className="text-red-600 hover:text-red-800">
                    <Trash2 className="w-5 h-5" />
                  </button>
                </div>
              </div>
            ))}
          </div>
        </div>
      </div>
    );
  };

  // ========== PROVEEDORES ==========
  const ProveedoresTab = () => {
    const [showForm, setShowForm] = useState(false);
    const [editando, setEditando] = useState(null);
    const [form, setForm] = useState({
      nombre: '', email: '', telefono: '', especialidad: '', servicios: []
    });

    const resetForm = () => {
      setForm({ nombre: '', email: '', telefono: '', especialidad: '', servicios: [] });
      setEditando(null);
      setShowForm(false);
    };

    const handleSubmit = async () => {
      try {
        const method = editando ? 'PUT' : 'POST';
        const body = editando ? {...form, id: editando} : form;
        
        await fetch(`${API_URL}/proveedores.php`, {
          method,
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(body)
        });
        
        mostrarAlerta(editando ? 'Proveedor actualizado' : 'Proveedor creado');
        resetForm();
        cargarDatos();
      } catch (error) {
        mostrarAlerta('Error al guardar proveedor', 'error');
      }
    };

    const eliminar = async (id) => {
      if (confirm('¿Eliminar este proveedor?')) {
        try {
          await fetch(`${API_URL}/proveedores.php?id=${id}`, { method: 'DELETE' });
          mostrarAlerta('Proveedor eliminado');
          cargarDatos();
        } catch (error) {
          mostrarAlerta('Error al eliminar proveedor', 'error');
        }
      }
    };

    const editar = (proveedor) => {
      setForm({
        ...proveedor,
        servicios: proveedor.servicios.map(s => s.id)
      });
      setEditando(proveedor.id);
      setShowForm(true);
    };

    const toggleServicio = (servicioId) => {
      const servicios = form.servicios.includes(servicioId)
        ? form.servicios.filter(id => id !== servicioId)
        : [...form.servicios, servicioId];
      setForm({...form, servicios});
    };

    return (
      <div className="space-y-4">
        <div className="bg-white rounded-lg shadow-lg p-6">
          <div className="flex justify-between items-center mb-6">
            <h2 className="text-2xl font-bold flex items-center gap-2">
              <Users className="w-6 h-6" />
              Proveedores de Servicios
            </h2>
            <button
              onClick={() => setShowForm(!showForm)}
              className="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 flex items-center gap-2"
            >
              <Plus className="w-4 h-4" />
              Nuevo Proveedor
            </button>
          </div>

          {showForm && (
            <div className="mb-6 p-4 bg-gray-50 rounded-lg space-y-3">
              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="block text-sm font-medium mb-1">Nombre *</label>
                  <input
                    type="text"
                    value={form.nombre}
                    onChange={(e) => setForm({...form, nombre: e.target.value})}
                    className="w-full px-3 py-2 border rounded"
                  />
                </div>
                <div>
                  <label className="block text-sm font-medium mb-1">Email</label>
                  <input
                    type="email"
                    value={form.email}
                    onChange={(e) => setForm({...form, email: e.target.value})}
                    className="w-full px-3 py-2 border rounded"
                  />
                </div>
              </div>
              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="block text-sm font-medium mb-1">Teléfono</label>
                  <input
                    type="tel"
                    value={form.telefono}
                    onChange={(e) => setForm({...form, telefono: e.target.value})}
                    className="w-full px-3 py-2 border rounded"
                  />
                </div>
                <div>
                  <label className="block text-sm font-medium mb-1">Especialidad</label>
                  <input
                    type="text"
                    value={form.especialidad}
                    onChange={(e) => setForm({...form, especialidad: e.target.value})}
                    className="w-full px-3 py-2 border rounded"
                  />
                </div>
              </div>
              <div>
                <label className="block text-sm font-medium mb-2">Servicios Asignados</label>
                <div className="grid grid-cols-3 gap-2">
                  {servicios.map(servicio => (
                    <label key={servicio.id} className="flex items-center gap-2 p-2 border rounded hover:bg-blue-50 cursor-pointer">
                      <input
                        type="checkbox"
                        checked={form.servicios.includes(servicio.id)}
                        onChange={() => toggleServicio(servicio.id)}
                        className="w-4 h-4"
                      />
                      <span className="text-sm">{servicio.nombre}</span>
                    </label>
                  ))}
                </div>
              </div>
              <div className="flex gap-2">
                <button onClick={handleSubmit} className="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
                  {editando ? 'Actualizar' : 'Crear'}
                </button>
                <button onClick={resetForm} className="bg-gray-300 px-4 py-2 rounded hover:bg-gray-400">
                  Cancelar
                </button>
              </div>
            </div>
          )}

          <div className="space-y-3">
            {proveedores.map(proveedor => (
              <div key={proveedor.id} className="border rounded-lg p-4 flex justify-between items-start">
                <div className="flex-1">
                  <div className="font-semibold text-lg">{proveedor.nombre}</div>
                  {proveedor.especialidad && (
                    <div className="text-sm text-gray-600 italic">{proveedor.especialidad}</div>
                  )}
                  <div className="text-sm text-gray-600 mt-1">
                    {proveedor.email && <span>📧 {proveedor.email}</span>}
                    {proveedor.telefono && <span className="ml-3">📞 {proveedor.telefono}</span>}
                  </div>
                  {proveedor.servicios && proveedor.servicios.length > 0 && (
                    <div className="mt-2 flex flex-wrap gap-1">
                      {proveedor.servicios.map(servicio => (
                        <span key={servicio.id} className="px-2 py-1 bg-blue-100 text-blue-800 text-xs rounded">
                          {servicio.nombre}
                        </span>
                      ))}
                    </div>
                  )}
                </div>
                <div className="flex gap-2">
                  <button onClick={() => editar(proveedor)} className="text-blue-600 hover:text-blue-800">
                    <Edit className="w-5 h-5" />
                  </button>
                  <button onClick={() => eliminar(proveedor.id)} className="text-red-600 hover:text-red-800">
                    <Trash2 className="w-5 h-5" />
                  </button>
                </div>
              </div>
            ))}
            {proveedores.length === 0 && (
              <div className="text-center text-gray-500 py-8">No hay proveedores registrados</div>
            )}
          </div>
        </div>
      </div>
    );
  };

  // ========== DISPONIBILIDAD ==========
  const DisponibilidadTab = () => {
    const [showForm, setShowForm] = useState(false);
    const [form, setForm] = useState({
      dia_semana: '', hora_inicio: '', hora_fin: '', duracion_cita: '60'
    });

    const dias = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];

    const handleSubmit = async () => {
      await fetch(`${API_URL}/disponibilidad.php`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(form)
      });
      mostrarAlerta('Horario agregado');
      setForm({ dia_semana: '', hora_inicio: '', hora_fin: '', duracion_cita: '60' });
      setShowForm(false);
      cargarDatos();
    };

    const eliminar = async (id) => {
      if (confirm('¿Eliminar este horario?')) {
        await fetch(`${API_URL}/disponibilidad.php?id=${id}`, { method: 'DELETE' });
        mostrarAlerta('Horario eliminado');
        cargarDatos();
      }
    };

    return (
      <div className="bg-white rounded-lg shadow p-6">
        <div className="flex justify-between items-center mb-6">
          <h2 className="text-2xl font-bold flex items-center gap-2">
            <Clock className="w-6 h-6" />
            Horarios Disponibles
          </h2>
          <button
            onClick={() => setShowForm(!showForm)}
            className="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 flex items-center gap-2"
          >
            <Plus className="w-4 h-4" />
            Nuevo Horario
          </button>
        </div>

        {showForm && (
          <div className="mb-6 p-4 bg-gray-50 rounded-lg space-y-3">
            <div>
              <label className="block text-sm font-medium mb-1">Día de la Semana</label>
              <select
                value={form.dia_semana}
                onChange={(e) => setForm({...form, dia_semana: e.target.value})}
                className="w-full px-3 py-2 border rounded"
              >
                <option value="">Seleccionar...</option>
                {dias.map((dia, idx) => (
                  <option key={idx} value={idx}>{dia}</option>
                ))}
              </select>
            </div>
            <div className="grid grid-cols-3 gap-3">
              <div>
                <label className="block text-sm font-medium mb-1">Hora Inicio</label>
                <input
                  type="time"
                  value={form.hora_inicio}
                  onChange={(e) => setForm({...form, hora_inicio: e.target.value})}
                  className="w-full px-3 py-2 border rounded"
                />
              </div>
              <div>
                <label className="block text-sm font-medium mb-1">Hora Fin</label>
                <input
                  type="time"
                  value={form.hora_fin}
                  onChange={(e) => setForm({...form, hora_fin: e.target.value})}
                  className="w-full px-3 py-2 border rounded"
                />
              </div>
              <div>
                <label className="block text-sm font-medium mb-1">Duración (min)</label>
                <input
                  type="number"
                  value={form.duracion_cita}
                  onChange={(e) => setForm({...form, duracion_cita: e.target.value})}
                  className="w-full px-3 py-2 border rounded"
                />
              </div>
            </div>
            <div className="flex gap-2">
              <button onClick={handleSubmit} className="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
                Agregar
              </button>
              <button onClick={() => setShowForm(false)} className="bg-gray-300 px-4 py-2 rounded">
                Cancelar
              </button>
            </div>
          </div>
        )}

        <div className="space-y-2">
          {disponibilidad.map(disp => (
            <div key={disp.id} className="border rounded-lg p-4 flex justify-between items-center">
              <div>
                <div className="font-semibold">{dias[disp.dia_semana]}</div>
                <div className="text-sm text-gray-600">
                  {disp.hora_inicio.substring(0,5)} - {disp.hora_fin.substring(0,5)} 
                  <span className="ml-2">({disp.duracion_cita} min por cita)</span>
                </div>
              </div>
              <button onClick={() => eliminar(disp.id)} className="text-red-600 hover:text-red-800">
                <Trash2 className="w-5 h-5" />
              </button>
            </div>
          ))}
        </div>
      </div>
    );
  };

  // ========== CITAS ==========
  const CitasTab = () => {
    const [filtroFecha, setFiltroFecha] = useState('');
    const [filtroProveedor, setFiltroProveedor] = useState('');
    const [vistaCalendario, setVistaCalendario] = useState(false);
    const [mesActual, setMesActual] = useState(new Date());
    const [mostrarFormulario, setMostrarFormulario] = useState(false);
    const [horariosDisponibles, setHorariosDisponibles] = useState([]);
    const [proveedoresServicio, setProveedoresServicio] = useState([]);
    const [formCita, setFormCita] = useState({
      servicio_id: '',
      proveedor_id: '',
      asignacion_automatica: false,
      cliente_id: '',
      cliente_nuevo: false,
      nombre_cliente: '',
      email_cliente: '',
      telefono_cliente: '',
      fecha: '',
      hora_inicio: '',
      hora_fin: '',
      num_integrantes: 1,
      notas: ''
    });
    const [diaSeleccionado, setDiaSeleccionado] = useState(null);

    useEffect(() => {
      if (formCita.servicio_id && formCita.fecha) {
        cargarHorariosDisponibles();
      }
      if (formCita.servicio_id) {
        cargarProveedoresServicio();
      }
    }, [formCita.servicio_id, formCita.fecha]);

    const cargarProveedoresServicio = async () => {
      try {
        const res = await fetch(`${API_URL}/proveedores.php?servicio_id=${formCita.servicio_id}&activo=true`);
        const data = await res.json();
        setProveedoresServicio(data);
      } catch (error) {
        console.error('Error al cargar proveedores:', error);
        setProveedoresServicio([]);
      }
    };

    const cargarHorariosDisponibles = async () => {
      try {
        const res = await fetch(`${API_URL}/horarios-disponibles.php?servicio_id=${formCita.servicio_id}&fecha=${formCita.fecha}`);
        const data = await res.json();
        //console.log(data);
        setHorariosDisponibles(data.horarios || []);
      } catch (error) {
        console.error('Error al cargar horarios:', error);
        setHorariosDisponibles([]);
      }
    };

    const citasFiltradas = citas.filter(c => {
      let cumpleFiltros = true;
      if (filtroFecha) cumpleFiltros = cumpleFiltros && c.fecha === filtroFecha;
      if (filtroProveedor) cumpleFiltros = cumpleFiltros && c.proveedor_id == filtroProveedor;
      return cumpleFiltros;
    });

    const cambiarEstado = async (id, estado) => {
      await fetch(`${API_URL}/citas.php`, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id, estado })
      });
      mostrarAlerta('Estado actualizado');
      cargarDatos();
    };

    const abrirFormularioNuevaCita = () => {
      setFormCita({
        servicio_id: '',
        proveedor_id: '',
        asignacion_automatica: false,
        cliente_id: '',
        cliente_nuevo: false,
        nombre_cliente: '',
        email_cliente: '',
        telefono_cliente: '',
        fecha: '',
        hora_inicio: '',
        hora_fin: '',
        num_integrantes: 1,
        notas: ''
      });
      setMostrarFormulario(true);
      //console.log(mostrarFormulario);
    };

    const cerrarFormulario = () => {
      setMostrarFormulario(false);
      setHorariosDisponibles([]);
      setProveedoresServicio([]);
    };

    const seleccionarHorario = (horario) => {
      setFormCita({
        ...formCita,
        hora_inicio: horario.hora_inicio,
        hora_fin: horario.hora_fin
      });
    };

    const agendarCita = async () => {
      try {
        let clienteId = formCita.cliente_id;
        setLoading(true);

        // Si es cliente nuevo, crearlo primero
        if (formCita.cliente_nuevo) {
          if (!formCita.nombre_cliente || !formCita.email_cliente) {
            setLoading(false);
            mostrarAlerta('Complete los datos del cliente', 'error');
            return;
          }

          const resCliente = await fetch(`${API_URL}/clientes.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
              nombre: formCita.nombre_cliente,
              email: formCita.email_cliente,
              telefono: formCita.telefono_cliente
            })
          });

          const dataCliente = await resCliente.json();
          clienteId = dataCliente.id;
        }

        if (!clienteId || !formCita.servicio_id || !formCita.fecha || !formCita.hora_inicio) {
          setLoading(false);
          alert('Complete todos los campos obligatorios');
          return;
        }

        // Crear la cita
        const resCita = await fetch(`${API_URL}/citas.php`, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            servicio_id: formCita.servicio_id,
            proveedor_id: formCita.asignacion_automatica ? null : (formCita.proveedor_id || null),
            asignacion_automatica: formCita.asignacion_automatica,
            cliente_id: clienteId,
            fecha: formCita.fecha,
            hora_inicio: formCita.hora_inicio,
            hora_fin: formCita.hora_fin,
            num_integrantes: formCita.num_integrantes,
            notas: formCita.notas
          })
        });
        //console.log(resCita);

        if (resCita.ok) {
          alert('Cita agendada exitosamente');
          cerrarFormulario();
          cargarDatos();
          setLoading(false);
        } else {
          const error = await resCita.json();
          alert(error.mensaje || 'Error al agendar la cita', 'error');
          setLoading(false);
        }
      } catch (error) {
        setLoading(false);
        alert('Error al procesar la solicitud', 'error');
      }
    };

    // Manejador de cambios en el formulario
    const handleChange = (e) => {
      const { name, value, type, checked } = e.target;
      setFormCita({
        ...formCita,
        [name]: type === 'checkbox' ? checked : value
      });
    };

    const getFechaMinima = () => {
      return new Date().toISOString().split('T')[0];
    };

    // Funciones del calendario
    const getDiasDelMes = () => {
      const año = mesActual.getFullYear();
      const mes = mesActual.getMonth();
      const primerDia = new Date(año, mes, 1);
      const ultimoDia = new Date(año, mes + 1, 0);
      const diasPrevios = primerDia.getDay();
      const diasEnMes = ultimoDia.getDate();
      
      const dias = [];
      
      // Días del mes anterior
      for (let i = diasPrevios - 1; i >= 0; i--) {
        const fecha = new Date(año, mes, -i);
        dias.push({ fecha, esOtroMes: true });
      }
      
      // Días del mes actual
      for (let i = 1; i <= diasEnMes; i++) {
        const fecha = new Date(año, mes, i);
        dias.push({ fecha, esOtroMes: false });
      }
      
      // Días del siguiente mes
      const diasRestantes = 42 - dias.length;
      for (let i = 1; i <= diasRestantes; i++) {
        const fecha = new Date(año, mes + 1, i);
        dias.push({ fecha, esOtroMes: true });
      }
      
      return dias;
    };

    const getCitasDelDia = (fecha) => {
      const fechaStr = fecha.toISOString().split('T')[0];
      return citas.filter(c => c.fecha === fechaStr);
    };

    const cambiarMes = (direccion) => {
      const nuevoMes = new Date(mesActual);
      nuevoMes.setMonth(nuevoMes.getMonth() + direccion);
      setMesActual(nuevoMes);
    };

    const esHoy = (fecha) => {
      const hoy = new Date();
      return fecha.toDateString() === hoy.toDateString();
    };

    const mesesNombres = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
                          'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
    const diasNombres = ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'];

    const dias = getDiasDelMes();

    return (
      <div className="bg-white rounded-2xl shadow-xl overflow-hidden">
        {/* Formulario de nueva cita bg-white rounded-lg shadow p-6"> */}
        {mostrarFormulario && (
          <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
            <div className="bg-white rounded-2xl shadow-2xl max-w-2xl w-full max-h-[90vh] overflow-y-auto">
              <div className="bg-gradient-to-r from-green-600 to-emerald-600 p-6 text-white">
                <div className="flex justify-between items-center">
                  <h2 className="text-2xl font-bold flex items-center gap-2">
                    Nueva Cita
                  </h2>
                  <button
                    onClick={cerrarFormulario}
                    className="text-white hover:text-gray-200 transition"
                  >
                    <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                    </svg>
                  </button>
                </div>
              </div>

              <div className="p-6 space-y-4">
                {/* Cliente Nuevo Toggle */}
                <div className="flex items-center space-x-3">
                  <input
                    type="checkbox"
                    name="cliente_nuevo"
                    checked={formCita.cliente_nuevo}
                    onChange={handleChange}
                    className="w-5 h-5"
                  />
                  <label className="font-medium text-gray-700">¿Cliente nuevo?</label>
                </div>

                {/* Campos según tipo de cliente */}
                {formCita.cliente_nuevo ? (
                  <>
                    <input
                      type="text"
                      name="nombre_cliente"
                      placeholder="Nombre del cliente *"
                      value={formCita.nombre_cliente}
                      onChange={handleChange}
                      className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500"
                    />
                    <input
                      type="email"
                      name="email_cliente"
                      placeholder="Email *"
                      value={formCita.email_cliente}
                      onChange={handleChange}
                      className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500"
                    />
                    <input
                      type="tel"
                      name="telefono_cliente"
                      placeholder="Teléfono"
                      value={formCita.telefono_cliente}
                      onChange={handleChange}
                      className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500"
                    />
                  </>
                ) : (
                  <select
                    value={formCita.cliente_id}
                    onChange={(e) => setFormCita({...formCita, cliente_id: e.target.value})}
                    className="w-full px-3 py-2 border rounded"
                  >
                    <option value="">Selecionar cliente</option>
                    {clientes.map(cliente => (
                      <option key={cliente.id} value={cliente.id}>
                        {cliente.nombre}
                      </option>
                    ))}
                  </select>
                )}

                {/* Servicio */}
                  <select
                    value={formCita.servicio_id}
                    onChange={(e) => setFormCita({...formCita, servicio_id: e.target.value})}
                    className="w-full px-3 py-2 border rounded"
                  >
                    <option value="">Selecionar servicio</option>
                    {servicios.map(servicio => (
                      <option key={servicio.id} value={servicio.id}>
                        {servicio.nombre}
                      </option>
                    ))}
                  </select>

                {/* Proveedor (solo si no es automático) */}
                {formCita.servicio_id && (
                  <select
                    value={formCita.proveedor_id}
                    onChange={(e) => setFormCita({...formCita, proveedor_id: e.target.value})}
                    className="w-full px-3 py-2 border rounded"
                  >
                    <option value="">Selecionar proveedor</option>
                    {proveedoresServicio.map(prov_serv => (
                      <option key={prov_serv.id} value={prov_serv.id}>
                        {prov_serv.nombre}
                      </option>
                    ))}
                  </select>
                )}

                {/* Fecha y hora */}
                <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                  <input
                    type="date"
                    name="fecha"
                    value={formCita.fecha}
                    onChange={handleChange}
                    className="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500"
                  />
                </div>
                {/* Horarios disponibles (ejemplo) */}
                {horariosDisponibles.length > 0 && (
                  <div className="mt-6">
                    <h3 className="font-bold text-gray-800 mb-3">Horarios Disponibles:</h3>
                    <div className="grid grid-cols-2 md:grid-cols-4 gap-2">
                      {horariosDisponibles.map((horario, index) => (
                        <button
                          key={index}
                          onClick={() => horario.disponible && seleccionarHorario(horario)}
                          disabled={!horario.disponible}
                          className={`p-4 rounded-lg border-2 transition-all ${
                              horario.disponible
                                  ? 'bg-blue-100 hover:bg-blue-200 px-4 py-2 rounded-lg transition'
                                  : 'border-gray-200 bg-gray-100 text-gray-400 cursor-not-allowed'
                          }`}
                        >
                          {horario.hora_inicio} - {horario.hora_fin}
                        </button>
                      ))}
                    </div>
                  </div>
                )}

                {/* Número de integrantes */}
                <input
                  type="number"
                  name="num_integrantes"
                  placeholder="Número de integrantes"
                  value={formCita.num_integrantes}
                  onChange={handleChange}
                  min="1"
                  className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500"
                />

                {/* Notas */}
                <textarea
                  name="notas"
                  placeholder="Notas adicionales"
                  value={formCita.notas}
                  onChange={handleChange}
                  rows="3"
                  className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500"
                />
              </div>
              <div className="bg-gray-50 p-6 flex gap-3 justify-end border-t">
                {/* Botones */}
                <div className="flex gap-3 mt-6">
                  <button
                    onClick={agendarCita}
                    className="flex bg-indigo-600 hover:bg-indigo-700 text-white px-6 py-2 rounded-lg font-semibold transition items-center gap-2"
                  >
                    {loading ? (
                      <>
                        <RefreshCw size={18} className="animate-spin" />
                        Guardando...
                      </>
                    ) : (
                      <>
                        <Plus size={18} />
                        Guardar Producto
                      </>
                    )}
                  </button>
                  <button
                    onClick={cerrarFormulario}
                    className="flex-1 bg-gray-300 hover:bg-gray-400 text-gray-800 py-3 rounded-lg font-semibold transition"
                  >
                    Cancelar
                  </button>
                </div>
              </div>
            </div>
          </div>
        )}
        <div className="flex justify-between items-center mb-6">
          <h2 className="text-2xl font-bold mb-6 flex items-center gap-2">
            <Calendar className="w-6 h-6" />
            Citas Agendadas
          </h2>
          <button
            onClick={abrirFormularioNuevaCita}
            className="flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 disabled:opacity-50"
          >
            + Nueva Cita
          </button>
          <button
            onClick={() => setVistaCalendario(!vistaCalendario)}
            className="flex items-center gap-2 px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 disabled:opacity-50"
          >
            {vistaCalendario ? (<Eye className="w-4 h-4" />):(<EyeClosed className="w-4 h-4" />)}
            Calendario
          </button>
        </div>
        {vistaCalendario ? (
          <div className="max-w-6xl mx-auto p-6 bg-gradient-to-br from-blue-50 to-indigo-50 min-h-screen">
            <div className="bg-white rounded-2xl shadow-xl p-6">
              {/* Header */}
              <div className="flex items-center justify-between mb-6 pb-4 border-b-2 border-indigo-100">
                <div className="flex items-center gap-3">
                  <Calendar className="w-8 h-8 text-indigo-600" />
                  <h1 className="text-3xl font-bold text-gray-800">Calendario de Citas</h1>
                </div>
                <div className="flex items-center gap-4">
                  <button
                    onClick={() => cambiarMes(-1)}
                    className="p-2 hover:bg-indigo-100 rounded-lg transition-colors"
                  >
                    <ChevronLeft className="w-6 h-6 text-indigo-600" />
                  </button>
                  <h2 className="text-xl font-semibold text-gray-700 min-w-[200px] text-center">
                    {mesesNombres[mesActual.getMonth()]} {mesActual.getFullYear()}
                  </h2>
                  <button
                    onClick={() => cambiarMes(1)}
                    className="p-2 hover:bg-indigo-100 rounded-lg transition-colors"
                  >
                    <ChevronRight className="w-6 h-6 text-indigo-600" />
                  </button>
                </div>
              </div>

              {/* Días de la semana */}
              <div className="grid grid-cols-7 gap-2 mb-2">
                {diasNombres.map(dia => (
                  <div key={dia} className="text-center font-semibold text-gray-600 py-2">
                    {dia}
                  </div>
                ))}
              </div>

              {/* Grid del calendario */}
              <div className="grid grid-cols-7 gap-2">
                {dias.map((dia, index) => {
                  const citasDelDia = getCitasDelDia(dia.fecha);
                  const esHoyDia = esHoy(dia.fecha);
                  const esPasado = dia.fecha < new Date(new Date().setHours(0, 0, 0, 0));
                  
                  return (
                    <div
                      key={index}
                      onClick={() => setDiaSeleccionado(dia.fecha)}
                      className={`
                        min-h-[100px] p-2 border rounded-lg cursor-pointer transition-all
                        ${dia.esOtroMes ? 'bg-gray-50 text-gray-400' : 'bg-white hover:bg-indigo-50'}
                        ${esHoyDia ? 'ring-2 ring-indigo-500 bg-indigo-50' : ''}
                        ${esPasado && !esHoyDia ? 'opacity-50' : ''}
                        ${diaSeleccionado?.toDateString() === dia.fecha.toDateString() ? 'ring-2 ring-blue-400' : ''}
                      `}
                    >
                      <div className={`
                        text-sm font-medium mb-1
                        ${esHoyDia ? 'text-indigo-600 font-bold' : 'text-gray-700'}
                      `}>
                        {dia.fecha.getDate()}
                      </div>
                      
                      {citasDelDia.length > 0 && (
                        <div className="space-y-1">
                          {citasDelDia.slice(0, 2).map(cita => (
                            <div
                              key={cita.id}
                              className={`
                                text-xs p-1 rounded truncate ${
                                  cita.estado === 'confirmada' ? 'bg-green-100 text-green-800' :
                                  cita.estado === 'pendiente' ? 'bg-yellow-100 text-yellow-800' :
                                    'bg-red-100 text-red-800'}
                              `}
                            >
                              <Clock className="w-3 h-3 inline mr-1" />
                              {cita.hora_inicio}
                            </div>
                          ))}
                          {citasDelDia.length > 2 && (
                            <div className="text-xs text-gray-500 font-medium">
                              +{citasDelDia.length - 2} más
                            </div>
                          )}
                        </div>
                      )}
                    </div>
                  );
                })}
              </div>

              {/* Panel de detalles del día seleccionado */}
              {diaSeleccionado && (
                <div className="mt-6 p-4 bg-indigo-50 rounded-lg border-2 border-indigo-200">
                  <h3 className="text-lg font-semibold text-gray-800 mb-3">
                    Citas del {diaSeleccionado.toLocaleDateString('es-ES', { 
                      weekday: 'long', 
                      year: 'numeric', 
                      month: 'long', 
                      day: 'numeric' 
                    })}
                  </h3>
                  {getCitasDelDia(diaSeleccionado).length > 0 ? (
                    <div className="space-y-2">
                      {getCitasDelDia(diaSeleccionado).map(cita => (
                        <div key={cita.id} className="bg-white p-3 rounded-lg shadow-sm">
                          <div className="flex justify-between items-center">
                            <div>
                              <p className="font-semibold text-lg">{cita.servicio_nombre}</p>
                              <p className="font-semibold text-gray-800">{cita.cliente_nombre}</p>
                              <p className="text-sm text-gray-600 flex items-center gap-1">
                                <Clock className="w-4 h-4" />
                                {cita.hora_inicio.substring(0,5)} - {cita.hora_fin.substring(0,5)}
                              </p>
                              <p className="text-sm mb-2">
                                <Users className="inline w-4 h-4 ml-2" /> {cita.num_integrantes} persona(s)
                                👤 {cita.proveedor_nombre}
                              </p>
                              {cita.num_integrantes > cita.max_integrantes * 0.8 && (
                                <div className="flex items-center gap-2 text-orange-600 text-sm mb-2">
                                  <AlertCircle className="w-4 h-4" />
                                  Cerca del límite ({cita.num_integrantes}/{cita.max_integrantes})
                                </div>
                              )}
                              {cita.notas && (
                                <div className="text-sm text-gray-600 italic">Notas: {cita.notas}</div>
                              )}
                              {cita.estado === 'pendiente' && (
                                <div className="flex gap-2 mt-3">
                                  <button
                                    onClick={() => cambiarEstado(cita.id, 'confirmada')}
                                    className="bg-green-600 text-white px-3 py-1 rounded text-sm hover:bg-green-700"
                                  >
                                    Confirmar
                                  </button>
                                  <button
                                    onClick={() => cambiarEstado(cita.id, 'cancelada')}
                                    className="bg-red-600 text-white px-3 py-1 rounded text-sm hover:bg-red-700"
                                  >
                                    Cancelar
                                  </button>
                                </div>
                              )}
                            </div>
                            <span className={`
                              px-3 py-1 rounded-full text-xs font-medium ${
                                cita.estado === 'confirmada' ? 'bg-green-100 text-green-800' :
                                cita.estado === 'pendiente' ? 'bg-yellow-100 text-yellow-800' :
                                'bg-red-100 text-red-800'}
                            `}>
                              {cita.estado}
                            </span>
                          </div>
                        </div>
                      ))}
                    </div>
                  ) : (
                    <p className="text-gray-600">No hay citas programadas para este día.</p>
                  )}
                </div>
              )}
            </div>
          </div>
        ) : (
        <div>
          <div className="mb-4 grid grid-cols-2 gap-4">
            <div>
              <label className="block text-sm font-medium mb-1">Filtrar por fecha</label>
              <input
                type="date"
                value={filtroFecha}
                onChange={(e) => setFiltroFecha(e.target.value)}
                className="px-3 py-2 border rounded"
              />
            </div>
            <div>
              <label className="block text-sm font-medium mb-1">Filtrar por proveedor</label>
              <select
                value={filtroProveedor}
                onChange={(e) => setFiltroProveedor(e.target.value)}
                className="w-full px-3 py-2 border rounded"
              >
                <option value="">Todos los proveedores</option>
                {proveedores.filter(p => p.activo).map(proveedor => (
                  <option key={proveedor.id} value={proveedor.id}>
                    {proveedor.nombre}
                  </option>
                ))}
                <option value="sin-proveedor">Sin proveedor asignado</option>
              </select>
            </div>
          </div>

          <div className="space-y-3">
            {citasFiltradas.map(cita => (
              <div key={cita.id} className="border rounded-lg p-4">
                <div className="flex justify-between items-start mb-2">
                  <div>
                    <div className="font-semibold text-lg">{cita.servicio_nombre}</div>
                    <div className="text-sm text-gray-600">
                      <strong>{cita.cliente_nombre}</strong> • {cita.cliente_email} • {cita.cliente_telefono}
                    </div>
                  </div>
                  <span className={`px-3 py-1 rounded-full text-sm ${
                    cita.estado === 'confirmada' ? 'bg-green-100 text-green-800' :
                    cita.estado === 'pendiente' ? 'bg-yellow-100 text-yellow-800' :
                    'bg-red-100 text-red-800'
                  }`}>
                    {cita.estado}
                  </span>
                </div>
                <div className="text-sm mb-2">
                  📅 {cita.fecha} • ⏰ {cita.hora_inicio.substring(0,5)} - {cita.hora_fin.substring(0,5)} • 
                  <Users className="inline w-4 h-4 ml-2" /> {cita.num_integrantes} personas
                  {cita.proveedor_nombre && (
                    <span className="ml-2">• 👤 {cita.proveedor_nombre}</span>
                  )}
                  {cita.asignacion_automatica && (
                    <span className="ml-2 text-xs bg-purple-100 text-purple-800 px-2 py-1 rounded">Auto-asignado</span>
                  )}
                </div>
                {cita.num_integrantes > cita.max_integrantes * 0.8 && (
                  <div className="flex items-center gap-2 text-orange-600 text-sm mb-2">
                    <AlertCircle className="w-4 h-4" />
                    Cerca del límite ({cita.num_integrantes}/{cita.max_integrantes})
                  </div>
                )}
                {cita.notas && (
                  <div className="text-sm text-gray-600 italic">Notas: {cita.notas}</div>
                )}
                {cita.estado === 'pendiente' && (
                  <div className="flex gap-2 mt-3">
                    <button
                      onClick={() => cambiarEstado(cita.id, 'confirmada')}
                      className="bg-green-600 text-white px-3 py-1 rounded text-sm hover:bg-green-700"
                    >
                      Confirmar
                    </button>
                    <button
                      onClick={() => cambiarEstado(cita.id, 'cancelada')}
                      className="bg-red-600 text-white px-3 py-1 rounded text-sm hover:bg-red-700"
                    >
                      Cancelar
                    </button>
                  </div>
                )}
              </div>
            ))}
            {citasFiltradas.length === 0 && (
              <div className="text-center text-gray-500 py-8">No hay citas agendadas</div>
            )}
          </div>
        </div>
        )}
      </div>
    );
  };
  

  return (
    <div className="min-h-screen bg-gray-100">
      {/* Alerta */}
      {alerta && (
        <div className={`fixed top-4 right-4 px-6 py-3 rounded-lg shadow-lg z-50 flex items-center gap-2 ${
          alerta.tipo === 'error' ? 'bg-red-500 text-white' : 'bg-green-500 text-white'
        }`}>
          {alerta.tipo === 'error' ? <AlertCircle className="w-5 h-5" /> : <Check className="w-5 h-5" />}
          {alerta.mensaje}
        </div>
      )}

      {/* Header */}
      <div className="bg-blue-600 text-white p-4 shadow-lg">
        <h1 className="text-2xl font-bold">Agenda de servicios</h1>
      </div>

      {/* Tabs */}
      <div className="bg-white shadow">
        <div className="max-w-7xl mx-auto">
          <nav className="flex">
            {[
              { id: 'empresa', label: 'Empresa', icon: Briefcase },
              { id: 'servicios', label: 'Servicios', icon: Settings },
              { id: 'proveedores', label: 'Proveedores', icon: Contact },
              { id: 'disponibilidad', label: 'Disponibilidad', icon: Clock },
              { id: 'citas', label: 'Citas', icon: Calendar }
            ].map(tab => (
              <button
                key={tab.id}
                onClick={() => setActiveTab(tab.id)}
                className={`flex items-center gap-2 px-6 py-4 border-b-2 font-medium ${
                  activeTab === tab.id
                    ? 'border-blue-600 text-blue-600'
                    : 'border-transparent text-gray-500 hover:text-gray-700'
                }`}
              >
                <tab.icon className="w-5 h-5" />
                {tab.label}
              </button>
            ))}
          </nav>
        </div>
      </div>

      {/* Content */}
      <div className="max-w-7xl mx-auto p-6">
        {activeTab === 'empresa' && <EmpresaForm />}
        {activeTab === 'servicios' && <ServiciosTab />}
        {activeTab === 'proveedores' && <ProveedoresTab />}
        {activeTab === 'disponibilidad' && <DisponibilidadTab />}
        {activeTab === 'citas' && <CitasTab />}
      </div>
    </div>
  );
};

export default AdminPanel;