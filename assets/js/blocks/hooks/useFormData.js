import { useState } from 'react';

const useFormData = (initialState, onStateChange) => {
    const [formState, setFormState] = useState(initialState);

    const handleChange = e => {
        const { name, value } = e.target;
        const updatedState = { ...formState, [name]: value };
        setFormState(updatedState);
        onStateChange(updatedState);
    };

    const updateFormState = (fieldNameOrObject, value) => {
        const updatedState =
            typeof fieldNameOrObject === 'object' && fieldNameOrObject !== null
                ? { ...formState, ...fieldNameOrObject }
                : { ...formState, [fieldNameOrObject]: value };

        setFormState(updatedState);
        onStateChange(updatedState);
    };

    return { formState, handleChange, updateFormState };
};

export default useFormData;
