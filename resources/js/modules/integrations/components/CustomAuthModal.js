import {Modal} from "react-bootstrap";
import {Inertia} from "@inertiajs/inertia";
import {useState} from "react";

const defaultObject = {
    fields: [],
    meta: {}
}

const CustomAuthModal = ({show, provider, onClose}) => {
    const [errors, setErrors] = useState({});
    const required_fields = provider && provider.hasOwnProperty('required_fields') ? JSON.parse(provider.required_fields) : defaultObject;

    const handleSubmit = e => {
        e.preventDefault();

        const data = {};
        setErrors({});

        const formData = new FormData(e.currentTarget);
        for (let [key, value] of formData.entries()) {
            data[key] = value;
        }

        Inertia.post(
            route('integrations.authenticate', [provider.id]),
            data,
            {
                preserveState: true,
                onError: (err) => setErrors(err),
                onSuccess: () => {
                    Inertia.reload({only: ['categories']});
                    onClose();
                }
            }
        )
    }

    return (
        <Modal show={show} onHide={onClose} centered>
            <Modal.Header style={{paddingBottom: 0}} closeButton>
                <Modal.Title>Authenticate</Modal.Title>
            </Modal.Header>
            <Modal.Body>
                <form onSubmit={handleSubmit}>
                    {
                        required_fields.fields.map(field => (
                            <div key={field.name}>
                                <div className="form-group mb-1">
                                    <label htmlFor={field.name}>{field.label}</label>
                                    {field.name === 'private_key'
                                    ?
                                    <textarea name={field.name} id={field.name} className="form-control" defaultValue={field?.value} rows={5}/>
                                    :
                                    <input
                                        type={field.type ?? "text"}
                                        placeholder={field.placeholder}
                                        name={field.name}
                                        id={field.name}
                                        className="form-control"
                                        defaultValue={field?.value}
                                    />
                                }
                                    
                                </div>
                                {errors.hasOwnProperty(field.name) && (
                                    <div className="invalid-feedback d-block">
                                        {errors[field.name]}
                                    </div>
                                )}
                            </div>
                        ))
                    }
                    {
                        required_fields.meta?.instructions_url ? (
                            <p>
                                For more info on how to generate your API Key, please refer to the&nbsp;
                                <a
                                    href={required_fields.meta.instructions_url}
                                    target="_blank"
                                >
                                    docs
                                </a>
                            </p>
                        ) : null
                    }
                    {
                        required_fields.meta?.info_text ? (
                            <p>{required_fields.meta.info_text}</p>
                        ) : null
                    }
                    <button className="btn btn-primary d-block m-auto" type="submit">Connect</button>
                </form>
            </Modal.Body>
        </Modal>
    );
}

export default CustomAuthModal;