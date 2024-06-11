import { useState } from 'react';

const useFormData = (initialState, onStateChange) => {
  const [formState, setFormState] = useState(initialState);

  const handleChange = (e) => {
    const { name, value } = e.target;
    const updatedState = { ...formState, [name]: value };
    setFormState(updatedState);
    onStateChange(updatedState);
  };

  const updateFormState = (fieldName, value) => {
    const updatedState = { ...formState, [fieldName]: value };
    setFormState(updatedState);
    onStateChange(updatedState);
  };

  return { formState, handleChange, updateFormState };
};

export default useFormData;
